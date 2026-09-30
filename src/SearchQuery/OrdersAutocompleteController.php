<?php

namespace AppBundle\SearchQuery;

use ApiPlatform\Metadata\IriConverterInterface;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Autocomplete endpoints backing the "key:value" filters offered by the
 * search bar (see js/app/components/SearchQueryBar) for a given resource -
 * one action per searchable field. To add a new autocompletable field,
 * add an action here (route: /search-query/{resource}/autocomplete:{field})
 * and wire it up as a `type: 'async'` field in the frontend's field config.
 */
class OrdersAutocompleteController extends AbstractController
{
    // pg_trgm's SIMILARITY() is rarely exactly 0 for two arbitrary non-empty
    // strings, so "> 0" alone barely filters anything - Postgres's own
    // conventional relevance cutoff (pg_trgm.similarity_threshold) is 0.3.
    private const SIMILARITY_THRESHOLD = 0.3;

    // The same cutoff for the actions scoring with word_similarity() -
    // owner() and customer(). See findByNameSimilarity() for why it's the
    // inclusive bound there; customer() is inclusive for the same reason,
    // one word of a multi-word name landing exactly on 0.3.
    private const WORD_SIMILARITY_THRESHOLD = 0.3;

    #[Route(path: '/search-query/orders/autocomplete:owner', name: 'search_query_orders_autocomplete_owner', methods: ['GET'])]
    public function owner(
        Request $request,
        EntityManagerInterface $entityManager,
        LocalBusinessRepository $repository,
        IriConverterInterface $iriConverter
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $q = trim((string) $request->query->get('q', ''));

        if ('' === $q) {
            return new JsonResponse(['hits' => []]);
        }

        $hits = [];

        $owners = array_merge(
            $this->findByNameSimilarity($repository->createQueryBuilder('r'), 'r', $q),
            $this->findByNameSimilarity($entityManager->getRepository(Store::class)->createQueryBuilder('s'), 's', $q)
        );

        foreach ($owners as $owner) {
            $hits[] = [
                'label' => $owner->getName(),
                'value' => $iriConverter->getIriFromResource($owner),
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }

    #[Route(path: '/search-query/orders/autocomplete:number', name: 'search_query_orders_autocomplete_number', methods: ['GET'])]
    public function number(Request $request, OrderRepository $orderRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $q = trim((string) $request->query->get('q', ''));

        if ('' === $q) {
            return new JsonResponse(['hits' => []]);
        }

        $qb = $orderRepository->createQueryBuilder('o');
        $qb
            ->andWhere('SIMILARITY(o.number, :q) > :threshold')
            ->addOrderBy('SIMILARITY(o.number, :q)', 'DESC')
            ->setParameter('q', strtolower($q))
            ->setParameter('threshold', self::SIMILARITY_THRESHOLD)
            ->setMaxResults(10);

        $hits = [];
        $seen = [];

        /** @var Order $order */
        foreach ($qb->getQuery()->getResult() as $order) {

            // Order numbers are unique per order, but not enforced unique at
            // the DB level - dedupe defensively.
            if (isset($seen[$order->getNumber()])) {
                continue;
            }
            $seen[$order->getNumber()] = true;

            $hits[] = [
                'label' => $order->getNumber(),
                'value' => $order->getNumber(),
                // Shown as a muted second line in the suggestion list, to
                // tell otherwise-indistinguishable order numbers apart.
                'date' => $order->getShippingTimeRange()?->getLower()?->format(\DateTimeInterface::ATOM),
                'owner' => $this->resolveOwnerName($order),
                'customer' => $order->getCustomer()?->getEmail(),
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }

    /**
     * Names matching $q by trigram similarity, best first - pg_trgm rather
     * than a LIKE, so the match is case-insensitive (trigrams are lowercased
     * before comparing) and survives a typo: "fidu", "FIDU" and "fiducal" all
     * find "Fiducial".
     *
     * WORD_SIMILARITY rather than the SIMILARITY that number()/customer() use:
     * it scores the query against the best-matching run of words inside the
     * name instead of against the whole string, which is what a name like
     * "Fruits & légumes à domicile" needs - "legume" scores 0.3 against it,
     * where SIMILARITY manages only 0.1.
     *
     * Inclusive comparison because that 0.3 sits exactly on the threshold;
     * names that genuinely don't match score well below it (<= 0.17 across
     * the owners in this database).
     *
     * No index to add: a gin_trgm_ops index only serves the "%" operator, not
     * a function call, so one would go unused unless this were rewritten to
     * "<%". At these table sizes the seq scan costs nothing anyway.
     *
     * @return object[]
     */
    private function findByNameSimilarity(QueryBuilder $qb, string $alias, string $q): array
    {
        return $qb
            ->andWhere(sprintf('WORD_SIMILARITY(:q, %s.name) >= :threshold', $alias))
            ->addOrderBy(sprintf('WORD_SIMILARITY(:q, %s.name)', $alias), 'DESC')
            ->setParameter('q', $q)
            ->setParameter('threshold', self::WORD_SIMILARITY_THRESHOLD)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * The order's restaurant or store, mirroring how the orders list renders
     * its "owner" column (see _partials/order/list.html.twig) and what the
     * "owner:" filter matches against (see AppBundle\SearchQuery\Orders).
     */
    private function resolveOwnerName(Order $order): ?string
    {
        if ($order->hasVendor() && !$order->isMultiVendor()) {
            return $order->getVendor()?->getName();
        }

        return $order->getDelivery()?->getStore()?->getName();
    }

    /**
     * A customer is looked up by whatever the admin has at hand - an email,
     * a name, or a phone number read off an order - so all three are
     * searched at once, the best match first.
     *
     * Email and name are matched fuzzily (pg_trgm), so a half-remembered
     * spelling still finds the customer:
     *  - email with SIMILARITY(), scoring against the whole string, which is
     *    what an email is: one token, typed (nearly) in full;
     *  - name with WORD_SIMILARITY() against "firstName lastName", so typing
     *    just one of the two still scores 1 - see findByNameSimilarity() for
     *    the same reasoning applied to owner names.
     *
     * Phone numbers are matched exactly instead - see
     * CustomerSearch::phoneNeedle() for why.
     */
    #[Route(path: '/search-query/orders/autocomplete:customer', name: 'search_query_orders_autocomplete_customer', methods: ['GET'])]
    public function customer(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $q = trim((string) $request->query->get('q', ''));

        if ('' === $q) {
            return new JsonResponse(['hits' => []]);
        }

        $qb = $entityManager->getRepository(Customer::class)->createQueryBuilder('c');

        // A phone number scores 1 (an exact match, ranked above any fuzzy
        // one) or nothing at all. GREATEST ignores NULLs, so a customer with
        // no name still gets scored on their email.
        $scoreParts = [
            'SIMILARITY(c.emailCanonical, :q)',
            sprintf('WORD_SIMILARITY(:q, %s)', CustomerSearch::fullNameExpr('c')),
        ];

        if (null !== $phoneNeedle = CustomerSearch::phoneNeedle($q)) {
            $scoreParts[] = sprintf(
                'CASE WHEN %s LIKE :phone THEN 1 ELSE 0 END',
                CustomerSearch::phoneDigitsExpr('c')
            );
            $qb->setParameter('phone', '%' . $phoneNeedle . '%');
        }

        $score = sprintf('GREATEST(%s)', implode(', ', $scoreParts));

        $qb
            ->andWhere(sprintf('%s >= :threshold', $score))
            ->addOrderBy($score, 'DESC')
            ->setParameter('q', strtolower($q))
            ->setParameter('threshold', self::WORD_SIMILARITY_THRESHOLD)
            ->setMaxResults(10);

        $hits = [];

        /** @var Customer $customer */
        foreach ($qb->getQuery()->getResult() as $customer) {
            $fullName = trim($customer->getFullName());
            $hits[] = [
                'label' => '' !== $fullName ? sprintf('%s (%s)', $fullName, $customer->getEmail()) : $customer->getEmail(),
                'value' => $customer->getEmail(),
                // Shown as a muted second line, so a hit found by phone
                // number shows the number that matched.
                'telephone' => $customer->getPhoneNumber(),
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }
}
