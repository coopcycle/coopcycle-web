<?php

namespace AppBundle\Entity\Referral;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

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

    /**
     * @return array<string,int> Count of referrals per status, zero-filled
     *                            for statuses with no rows.
     */
    public function getStatusCounts(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.status AS status, COUNT(r.id) AS count')
            ->groupBy('r.status')
            ->getQuery()
            ->getResult();

        $counts = [
            Referral::STATUS_PENDING => 0,
            Referral::STATUS_COMPLETED => 0,
            Referral::STATUS_EXPIRED => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Distinct referrers with at least one completed referral, for
     * computing the admin dashboard's breakdown by level.
     *
     * @return Customer[]
     */
    public function findReferrersWithCompletedReferral(): array
    {
        $referrals = $this->createQueryBuilder('r')
            ->addSelect('referrer')
            ->join('r.referrer', 'referrer')
            ->andWhere('r.status = :status')
            ->setParameter('status', Referral::STATUS_COMPLETED)
            ->getQuery()
            ->getResult();

        $referrers = [];
        foreach ($referrals as $referral) {
            $referrers[$referral->getReferrer()->getId()] = $referral->getReferrer();
        }

        return array_values($referrers);
    }

    /**
     * For the admin referral list, newest first, with referrer/referred
     * eagerly fetched to avoid N+1 lookups in the template.
     */
    public function createListQueryBuilder(?string $status): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('referrer', 'referred')
            ->join('r.referrer', 'referrer')
            ->join('r.referred', 'referred')
            ->orderBy('r.createdAt', 'DESC');

        if (null !== $status) {
            $qb
                ->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        return $qb;
    }
}
