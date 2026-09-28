<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\Task;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts an Order query to invoiceable orders, shared by
 * InvoiceLineItemsProvider and InvoiceLineItemsGroupedByOrganizationProvider.
 *
 * Only "fulfilled" orders are invoiced — that's the state representing a
 * completed sale, for foodtech and last mile alike. Orders left in an
 * intermediary state ("new", "accepted") are not billable; they're surfaced
 * separately through applyPending() so admins can chase them up rather than
 * being silently invoiced.
 *
 * Orders whose tasks have all been cancelled are excluded either way.
 * Cancelling every task of a delivery is supposed to cancel the order too (see
 * MessageHandler\Task\Command\CancelHandler), but several code paths have
 * historically bypassed that, leaving cancelled deliveries billable. Filtering
 * on the tasks rather than on the order state alone keeps those out.
 *
 * This is a hard business rule, not something callers can override via a
 * `state[]` query param — hence living here rather than behind an ApiFilter.
 */
final class InvoiceLineItemStateFilter
{
    private const INVOICEABLE_STATE = 'fulfilled';
    private const PENDING_STATES = ['new', 'accepted'];

    /**
     * @param string $rootAlias the Order root alias
     */
    public function apply(QueryBuilder $qb, string $rootAlias): void
    {
        $qb->andWhere(sprintf('%s.state = :invoiceLineItemState', $rootAlias));
        $qb->setParameter('invoiceLineItemState', self::INVOICEABLE_STATE);

        $this->excludeFullyCancelledDeliveries($qb, $rootAlias);
    }

    /**
     * Orders that fall in the requested range but are still waiting for someone
     * to complete the workflow, and are therefore missing from the invoice.
     *
     * @param string $rootAlias the Order root alias
     */
    public function applyPending(QueryBuilder $qb, string $rootAlias): void
    {
        $qb->andWhere($qb->expr()->in(
            sprintf('%s.state', $rootAlias),
            ':invoiceLineItemPendingStates'
        ));
        $qb->setParameter('invoiceLineItemPendingStates', self::PENDING_STATES);

        $this->excludeFullyCancelledDeliveries($qb, $rootAlias);
    }

    private function excludeFullyCancelledDeliveries(QueryBuilder $qb, string $rootAlias): void
    {
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

        $qb->setParameter('invoiceLineItemCancelledStatus', Task::STATUS_CANCELLED);
    }
}
