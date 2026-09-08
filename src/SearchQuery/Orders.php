<?php

namespace AppBundle\SearchQuery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Customer;
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
 * prefixed with "-" to exclude instead of include. "owner" additionally
 * accepts several values ("owner:(A OR B)", built by the search bar's
 * checkbox dropdown - see SearchQueryParser), matching any of them.
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

        $ownerFilters = $searchQuery->getFilters('owner');
        $includedOwnerNames = array_values(array_map(
            fn ($filter) => $filter->value,
            array_filter($ownerFilters, fn ($filter) => !$filter->exclude)
        ));
        $excludedOwnerNames = array_values(array_map(
            fn ($filter) => $filter->value,
            array_filter($ownerFilters, fn ($filter) => $filter->exclude)
        ));

        if (count($includedOwnerNames) > 0) {
            $qb = $this->applyOwnerFilter($qb, $includedOwnerNames, exclude: false);
        }
        if (count($excludedOwnerNames) > 0) {
            $qb = $this->applyOwnerFilter($qb, $excludedOwnerNames, exclude: true);
        }

        return $qb;
    }

    /**
     * Applies an "owner:(A OR B)" / "-owner:(A OR B)" filter - matches (or
     * excludes) orders owned by any of the given store/restaurant names. A
     * single name ("owner:A") goes through the same path with $names = [A].
     *
     * @param string[] $names
     */
    private function applyOwnerFilter(QueryBuilder $qb, array $names, bool $exclude): QueryBuilder
    {
        $stores = [];
        $restaurants = [];

        foreach ($names as $name) {
            $owner = $this->resolveOwner($name);
            if ($owner instanceof Store) {
                $stores[] = $owner;
            } elseif ($owner instanceof LocalBusiness) {
                $restaurants[] = $owner;
            }
        }

        if (0 === count($stores) && 0 === count($restaurants)) {
            if ($exclude) {
                // Nothing resolved to exclude - a no-op, same as the
                // single-unknown-owner case below.
                return $qb;
            }
            // No matching owner at all: an inclusive filter should yield no
            // results, rather than being silently ignored.
            return $qb->andWhere('1 = 0');
        }

        $alias = $exclude ? 'owner_excl' : 'owner_incl';
        $conditions = [];

        if (count($stores) > 0) {
            $deliveryAlias = "d_{$alias}";
            $storeAlias = "s_{$alias}";
            $qb
                ->leftJoin(Delivery::class, $deliveryAlias, Expr\Join::WITH, "{$deliveryAlias}.order = o.id")
                ->leftJoin(Store::class, $storeAlias, Expr\Join::WITH, "{$deliveryAlias}.store = {$storeAlias}.id");

            $conditions[] = $exclude
                ? $qb->expr()->orX(
                    $qb->expr()->isNull("{$storeAlias}.id"),
                    $qb->expr()->notIn("{$storeAlias}.id", ":{$alias}_stores"),
                )
                : $qb->expr()->in("{$storeAlias}.id", ":{$alias}_stores");
            $qb->setParameter("{$alias}_stores", $stores);
        }

        if (count($restaurants) > 0) {
            $vendorAlias = "v_{$alias}";
            $subQb = $this->entityManager->createQueryBuilder();
            $subQb
                ->select('1')
                ->from(OrderVendor::class, $vendorAlias)
                ->andWhere("{$vendorAlias}.order = o.id")
                ->andWhere("{$vendorAlias}.restaurant IN (:{$alias}_restaurants)");

            $exists = $qb->expr()->exists($subQb->getDQL());
            $conditions[] = $exclude ? $qb->expr()->not($exists) : $exists;
            $qb->setParameter("{$alias}_restaurants", $restaurants);
        }

        // Include: matches if it's any of the given stores OR restaurants.
        // Exclude: must be none of the given stores AND none of the given
        // restaurants (each already negated above).
        $combined = count($conditions) > 1
            ? ($exclude ? $qb->expr()->andX(...$conditions) : $qb->expr()->orX(...$conditions))
            : $conditions[0];

        return $qb->andWhere($combined);
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
