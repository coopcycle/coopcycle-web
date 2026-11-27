<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Order\Model\OrderInterface;

class OrderTransferRepository extends EntityRepository
{
    /**
     * @return OrderTransfer[]
     */
    public function findByOrder(OrderInterface $order): array
    {
        return $this->findBy(['order' => $order], ['id' => 'ASC']);
    }

    public function findOneByIdempotencyKey(OrderInterface $order, string $idempotencyKey): ?OrderTransfer
    {
        return $this->findOneBy(['order' => $order, 'idempotencyKey' => $idempotencyKey]);
    }

    /**
     * What has actually left the platform for this order, net of reversals.
     * The settlement invariant is that this equals the restaurant's take
     * minus whatever Stripe fee the contract makes them bear.
     */
    public function getSettledAmount(OrderInterface $order): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount - t.reversedAmount), 0)')
            ->andWhere('t.order = :order')
            ->andWhere('t.state IN (:states)')
            ->setParameter('order', $order)
            ->setParameter('states', [
                OrderTransfer::STATE_SUCCEEDED,
                OrderTransfer::STATE_REVERSED_PARTIALLY,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasSucceededTransfer(OrderInterface $order): bool
    {
        $count = (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.order = :order')
            ->andWhere('t.state IN (:states)')
            ->setParameter('order', $order)
            ->setParameter('states', [
                OrderTransfer::STATE_SUCCEEDED,
                OrderTransfer::STATE_REVERSED_PARTIALLY,
                OrderTransfer::STATE_REVERSED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Rows that never reached a conclusion: written before the Stripe call,
     * with no answer recorded. Either the call never happened or the reply
     * was lost, and only a look at Stripe can tell which -- so these are
     * reconciled, never simply retried.
     */
    public function createUnresolvedQueryBuilder(\DateTime $before): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.state = :pending')
            ->andWhere('t.createdAt <= :before')
            ->setParameter('pending', OrderTransfer::STATE_PENDING)
            ->setParameter('before', $before)
            ->orderBy('t.createdAt', 'ASC');
    }

    /**
     * Settlements that need somebody to look at them: no account to pay, a
     * Stripe refusal, or an amount that didn't add up.
     */
    public function createNeedsAttentionQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.state IN (:states)')
            ->setParameter('states', [
                OrderTransfer::STATE_FAILED,
                OrderTransfer::STATE_BLOCKED_NO_ACCOUNT,
                OrderTransfer::STATE_MISMATCH,
            ])
            ->orderBy('t.createdAt', 'DESC');
    }
}
