<?php

namespace AppBundle\SearchQuery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\SearchQuery\SearchQueryParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

/**
 * Applies the "key:value" search query used on /admin/orders (see
 * js/app/components/SearchQueryBar) to a QueryBuilder for Sylius orders.
 *
 * Supported keys: number, customer, date, state, owner - each optionally
 * prefixed with "-" to exclude instead of include.
 *
 * Expects $qb to be a QueryBuilder over AppBundle\Entity\Sylius\Order with
 * root alias "o" (e.g. built via OrderRepository::createOptimizedQueryBuilder('o')).
 */
class Orders implements SearchQueryInterface
{
    public function __construct(
        private readonly SearchQueryParser $searchQueryParser,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function search(string $searchQueryString, QueryBuilder $qb): QueryBuilder
    {
        $searchQuery = $this->searchQueryParser->parse($searchQueryString);

        if ($numberFilter = $searchQuery->getFilter('number')) {
            $match = $qb->expr()->like('LOWER(o.number)', ':order_number');
            $qb
                ->andWhere($numberFilter->exclude ? $qb->expr()->not($match) : $match)
                ->setParameter('order_number', '%' . strtolower($numberFilter->value) . '%');
        }

        if ($customerFilter = $searchQuery->getFilter('customer')) {
            $needle = '%' . strtolower($customerFilter->value) . '%';
            $match = $qb->expr()->orX(
                $qb->expr()->like('LOWER(customer.emailCanonical)', ':customer_needle'),
                $qb->expr()->like('LOWER(customer.firstName)', ':customer_needle'),
                $qb->expr()->like('LOWER(customer.lastName)', ':customer_needle'),
            );
            $qb
                ->leftJoin(Customer::class, 'customer', Expr\Join::WITH, 'o.customer = customer.id')
                ->andWhere($customerFilter->exclude ? $qb->expr()->not($match) : $match)
                ->setParameter('customer_needle', $needle);
        }

        if ($dateFilter = $searchQuery->getFilter('date')) {
            try {
                $date = new \DateTimeImmutable($dateFilter->value);
                $overlaps = 'OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE';
                $qb
                    ->andWhere($dateFilter->exclude ? "NOT ($overlaps)" : $overlaps)
                    ->setParameter('range', sprintf('[%s, %s]', $date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')));
            } catch (\Exception $e) {
                // Ignore invalid date, e.g. while the user is still typing
            }
        }

        $stateFilters = $searchQuery->getFilters('state');
        $includedStates = array_values(array_map(
            fn ($filter) => $filter->value,
            array_filter($stateFilters, fn ($filter) => !$filter->exclude)
        ));
        $excludedStates = array_values(array_map(
            fn ($filter) => $filter->value,
            array_filter($stateFilters, fn ($filter) => $filter->exclude)
        ));

        // TODO Don't allow state=cart
        if (count($includedStates) > 0) {
            $qb
                ->andWhere('o.state IN (:state)')
                ->setParameter('state', $includedStates);
        } else {
            $qb
                ->andWhere('o.state != :state')
                ->setParameter('state', OrderInterface::STATE_CART);
        }

        if (count($excludedStates) > 0) {
            $qb
                ->andWhere('o.state NOT IN (:excluded_state)')
                ->setParameter('excluded_state', $excludedStates);
        }

        if ($ownerFilter = $searchQuery->getFilter('owner')) {

            $owner = $this->resolveOwner($ownerFilter->value);

            if ($owner instanceof Store) {
                $qb
                    ->join(Delivery::class, 'd', Expr\Join::WITH, 'd.order = o.id')
                    ->join(Store::class, 's', Expr\Join::WITH, 'd.store = s.id')
                    ->andWhere($ownerFilter->exclude ? $qb->expr()->neq('s.id', ':store') : $qb->expr()->eq('s.id', ':store'))
                    ->setParameter('store', $owner)
                    ;
            } elseif ($owner instanceof LocalBusiness) {
                if ($ownerFilter->exclude) {
                    $subQb = $this->entityManager->createQueryBuilder();
                    $subQb
                        ->select('1')
                        ->from(OrderVendor::class, 'v_excluded')
                        ->andWhere('v_excluded.order = o.id')
                        ->andWhere('v_excluded.restaurant = :restaurant');
                    $qb
                        ->andWhere($qb->expr()->not($qb->expr()->exists($subQb->getDQL())))
                        ->setParameter('restaurant', $owner);
                } else {
                    $qb = OrderRepository::addVendorClause($qb, 'o', $owner);
                }
            } elseif (!$ownerFilter->exclude) {
                // No matching owner: an inclusive filter should yield no results,
                // rather than being silently ignored. An exclusive filter on an
                // unknown owner excludes nothing, so it is a no-op.
                $qb->andWhere('1 = 0');
            }
        }

        return $qb;
    }

    /**
     * Resolves the "owner" filter value (a restaurant or store name, as
     * typed/selected in the search bar) to the matching entity.
     */
    private function resolveOwner(string $name): Store|LocalBusiness|null
    {
        $name = trim($name);

        if ('' === $name) {
            return null;
        }

        foreach ([LocalBusiness::class, Store::class] as $class) {
            $qb = $this->entityManager->getRepository($class)->createQueryBuilder('e');
            $qb
                ->andWhere('LOWER(e.name) = LOWER(:name)')
                ->setParameter('name', $name)
                ->setMaxResults(1);

            if ($match = $qb->getQuery()->getOneOrNullResult()) {
                return $match;
            }
        }

        // Fallback to a partial match, consistent with OrdersAutocompleteController::owner()
        foreach ([LocalBusiness::class, Store::class] as $class) {
            $qb = $this->entityManager->getRepository($class)->createQueryBuilder('e');
            $qb
                ->andWhere($qb->expr()->like('LOWER(e.name)', ':name'))
                ->setParameter('name', '%' . strtolower($name) . '%')
                ->setMaxResults(1);

            if ($match = $qb->getQuery()->getOneOrNullResult()) {
                return $match;
            }
        }

        return null;
    }
}
