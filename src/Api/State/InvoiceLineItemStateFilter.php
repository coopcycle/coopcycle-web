<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\Task;
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
 * It also excludes orders whose tasks have all been cancelled. Cancelling
 * every task of a delivery is supposed to cancel the order too (see
 * MessageHandler\Task\Command\CancelHandler), but several code paths have
 * historically bypassed that, leaving cancelled deliveries billable. Filtering
 * on the tasks rather than on the order state alone keeps those out.
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

        // Keep the order unless it has tasks and every single one is cancelled
        $qb->andWhere($qb->expr()->orX(
            sprintf(
                'NOT EXISTS (SELECT anyTask.id FROM %s anyTask JOIN anyTask.delivery anyDelivery WHERE anyDelivery.order = %s)',
                Task::class,
                $rootAlias
            ),
            sprintf(
                'EXISTS (SELECT activeTask.id FROM %s activeTask JOIN activeTask.delivery activeDelivery WHERE activeDelivery.order = %s AND activeTask.status != :invoiceLineItemCancelledStatus)',
                Task::class,
                $rootAlias
            )
        ));

        $qb->setParameter('invoiceLineItemLastMileStates', self::LAST_MILE_STATES);
        $qb->setParameter('invoiceLineItemFoodtechState', self::FOODTECH_STATE);
        $qb->setParameter('invoiceLineItemCancelledStatus', Task::STATUS_CANCELLED);
    }
}
