<?php

namespace AppBundle\Entity\Loyalty;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityRepository;
use Sylius\Component\Order\Model\OrderInterface;

class LoyaltyPointsEntryRepository extends EntityRepository
{
    /**
     * What's left on credits that haven't expired -- debits are already
     * reflected there, having been subtracted from $remaining when spent.
     */
    public function getBalance(Customer $customer, ?\DateTime $now = null): int
    {
        $balance = $this->createQueryBuilder('e')
            ->select('SUM(e.remaining)')
            ->andWhere('e.customer = :customer')
            ->andWhere('e.type = :credit')
            ->andWhere('e.expiresAt IS NULL OR e.expiresAt > :now')
            ->setParameter('customer', $customer)
            ->setParameter('credit', LoyaltyPointsEntry::TYPE_CREDIT)
            ->setParameter('now', $now ?? new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $balance;
    }

    /**
     * Oldest first, so points closest to expiring are spent before fresher
     * ones.
     *
     * $lockMode exists for spending: taking a write lock inside the
     * redemption transaction is what stops two concurrent redemptions both
     * reading the same balance and spending it twice.
     */
    public function findSpendableCredits(Customer $customer, ?\DateTime $now = null, ?int $lockMode = null): array
    {
        $query = $this->createQueryBuilder('e')
            ->andWhere('e.customer = :customer')
            ->andWhere('e.type = :credit')
            ->andWhere('e.remaining > 0')
            ->andWhere('e.expiresAt IS NULL OR e.expiresAt > :now')
            ->setParameter('customer', $customer)
            ->setParameter('credit', LoyaltyPointsEntry::TYPE_CREDIT)
            ->setParameter('now', $now ?? new \DateTime())
            ->orderBy('e.expiresAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery();

        if (null !== $lockMode) {
            $query->setLockMode($lockMode);
        }

        return $query->getResult();
    }

    public function findOneByOrder(OrderInterface $order): ?LoyaltyPointsEntry
    {
        return $this->findOneBy(['order' => $order, 'type' => LoyaltyPointsEntry::TYPE_CREDIT]);
    }

    public function createHistoryQueryBuilder(Customer $customer)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC');
    }
}
