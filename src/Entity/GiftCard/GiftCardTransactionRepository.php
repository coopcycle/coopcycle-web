<?php

namespace AppBundle\Entity\GiftCard;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class GiftCardTransactionRepository extends EntityRepository
{
    /**
     * The balance as the ledger sees it, which is the real one. The column on
     * the card is a cache of this; `coopcycle:gift-cards:check` compares the
     * two, and a mismatch means a bug worth stopping for.
     */
    public function getLedgerBalance(GiftCard $giftCard): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.giftCard = :giftCard')
            ->setParameter('giftCard', $giftCard)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The *live* hold for a payment leg, if there is one.
     *
     * Holds that have already been put back are excluded, and that
     * exclusion is load-bearing. The ledger is append-only, so releasing a
     * hold leaves the hold row in place and adds a refund row pointing at
     * it. Without this condition, a customer whose checkout failed and who
     * tried again would hit the released hold here, be handed it as though
     * the money were still reserved, and end up with an order part-paid by
     * a card that was never actually debited.
     */
    public function findHoldForPayment(GiftCard $giftCard, PaymentInterface $payment): ?GiftCardTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.giftCard = :giftCard')
            ->andWhere('t.payment = :payment')
            ->andWhere('t.type = :hold')
            ->andWhere('NOT EXISTS (SELECT r.id FROM AppBundle\\Entity\\GiftCard\\GiftCardTransaction r WHERE r.parent = t)')
            ->setParameter('giftCard', $giftCard)
            ->setParameter('payment', $payment)
            ->setParameter('hold', GiftCardTransaction::TYPE_HOLD)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRedemptionForPayment(GiftCard $giftCard, PaymentInterface $payment): ?GiftCardTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.giftCard = :giftCard')
            ->andWhere('t.payment = :payment')
            ->andWhere('t.type = :redemption')
            ->setParameter('giftCard', $giftCard)
            ->setParameter('payment', $payment)
            ->setParameter('redemption', GiftCardTransaction::TYPE_REDEMPTION)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Whether this leg has already been put back. Cancellation events get
     * redelivered, and without this check a replay would credit the card a
     * second time.
     */
    public function hasRefundForPayment(GiftCard $giftCard, PaymentInterface $payment): bool
    {
        $count = (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.giftCard = :giftCard')
            ->andWhere('t.payment = :payment')
            ->andWhere('t.type = :refund')
            ->setParameter('giftCard', $giftCard)
            ->setParameter('payment', $payment)
            ->setParameter('refund', GiftCardTransaction::TYPE_REFUND)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findOneByOrderAndType(OrderInterface $order, string $type): ?GiftCardTransaction
    {
        return $this->findOneBy(['order' => $order, 'type' => $type]);
    }

    public function createHistoryQueryBuilder(GiftCard $giftCard): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.giftCard = :giftCard')
            ->setParameter('giftCard', $giftCard)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC');
    }

    /**
     * Holds that outlived their order. A worker dying between authorizing a
     * payment and recording what happened to it leaves money reserved for an
     * order nobody is waiting on; the reconciliation sweep uses this to tell
     * those apart from holds that are simply still in flight.
     */
    public function createStaleHoldsQueryBuilder(?\DateTime $now = null): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :hold')
            ->andWhere('t.expiresAt IS NOT NULL')
            ->andWhere('t.expiresAt <= :now')
            ->setParameter('hold', GiftCardTransaction::TYPE_HOLD)
            ->setParameter('now', $now ?? new \DateTime())
            ->orderBy('t.expiresAt', 'ASC');
    }
}
