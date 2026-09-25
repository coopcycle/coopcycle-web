<?php

namespace AppBundle\SearchQuery;

use ApiPlatform\Metadata\IriConverterInterface;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
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

        $qb = $repository->createQueryBuilder('r');

        $fieldValue = $qb->expr()->literal('%' . $request->query->get('q') . '%');

        $qb->andWhere($qb->expr()->like('r.name', $fieldValue));

        $results = $qb->getQuery()->getResult();

        $hits = [];

        foreach ($results as $restaurant) {
            $hits[] = [
                'label' => $restaurant->getName(),
                'value' => $iriConverter->getIriFromResource($restaurant),
            ];
        }

        $qb = $entityManager->getRepository(Store::class)->createQueryBuilder('s');
        $qb->andWhere($qb->expr()->like('s.name', $fieldValue));

        $results = $qb->getQuery()->getResult();

        foreach ($results as $store) {
            $hits[] = [
                'label' => $store->getName(),
                'value' => $iriConverter->getIriFromResource($store),
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
        $qb
            ->andWhere('SIMILARITY(c.emailCanonical, :q) > :threshold')
            ->addOrderBy('SIMILARITY(c.emailCanonical, :q)', 'DESC')
            ->setParameter('q', strtolower($q))
            ->setParameter('threshold', self::SIMILARITY_THRESHOLD)
            ->setMaxResults(10);

        $results = $qb->getQuery()->getResult();

        $hits = [];

        /** @var Customer $customer */
        foreach ($results as $customer) {
            $fullName = trim($customer->getFullName());
            $hits[] = [
                'label' => '' !== $fullName ? sprintf('%s (%s)', $fullName, $customer->getEmail()) : $customer->getEmail(),
                'value' => $customer->getEmail(),
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }
}
