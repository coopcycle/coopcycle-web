<?php

namespace AppBundle\Entity\Referral;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityRepository;

class ReferralRepository extends EntityRepository
{
    public function findPendingByReferredCustomer(Customer $referred): ?Referral
    {
        return $this->findOneBy([
            'referred' => $referred,
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function countCompletedByReferrer(Customer $referrer): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.referrer = :referrer')
            ->andWhere('r.status = :status')
            ->setParameter('referrer', $referrer)
            ->setParameter('status', Referral::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Referral[]
     */
    public function findByReferrer(Customer $referrer): array
    {
        return $this->findBy(['referrer' => $referrer], ['createdAt' => 'DESC']);
    }

    /**
     * Pending referrals older than $ttlDays, for the expiry sweep.
     *
     * @return Referral[]
     */
    public function findExpirable(int $ttlDays): array
    {
        $threshold = new \DateTime(sprintf('-%d days', $ttlDays));

        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt < :threshold')
            ->setParameter('status', Referral::STATUS_PENDING)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }
}
