<?php

namespace AppBundle\Api\State;

use Doctrine\ORM\QueryBuilder;

/**
 * Restricts an Order query to invoiceable states, shared by
 * InvoiceLineItemsProvider and InvoiceLineItemsGroupedByOrganizationProvider.
 *
 * Foodtech (restaurant) orders are only invoiced once "fulfilled" — that's
 * the state that actually represents a completed sale. Everything else
 * (Store / on-demand-delivery orders) keeps the usual new/accepted/fulfilled
 * range, since those are invoiced for the delivery service itself regardless
 * of whether the underlying order has completed yet.
 *
 * This is a hard business rule, not something callers can override via a
 * `state[]` query param — hence living here rather than behind an ApiFilter.
 */
final class InvoiceLineItemStateFilter
{
    private const LAST_MILE_STATES = ['new', 'accepted', 'fulfilled'];
    private const FOODTECH_STATE = 'fulfilled';

    /**
     * @param string $rootAlias the Order root alias
     * @param string $vendorAlias alias of an existing `$rootAlias.vendors` left join
     */
    public function apply(QueryBuilder $qb, string $rootAlias, string $vendorAlias): void
    {
        // OrderVendor has a composite identifier (order, restaurant), no `id`
        // field to check — `restaurant` is a plain required field on it, so
        // it's non-null exactly when a vendor row is actually joined
        $vendorRestaurant = sprintf('%s.restaurant', $vendorAlias);

        $qb->andWhere($qb->expr()->orX(
            $qb->expr()->andX(
                $qb->expr()->isNull($vendorRestaurant),
                $qb->expr()->in(sprintf('%s.state', $rootAlias), ':invoiceLineItemLastMileStates')
            ),
            $qb->expr()->andX(
                $qb->expr()->isNotNull($vendorRestaurant),
                sprintf('%s.state = :invoiceLineItemFoodtechState', $rootAlias)
            )
        ));

        $qb->setParameter('invoiceLineItemLastMileStates', self::LAST_MILE_STATES);
        $qb->setParameter('invoiceLineItemFoodtechState', self::FOODTECH_STATE);
    }
}
