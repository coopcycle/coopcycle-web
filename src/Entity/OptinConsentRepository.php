<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class OptinConsentRepository extends EntityRepository
{
    /**
     * Of the customers given, those who actively consented to $type and
     * haven't withdrawn it.
     *
     * Deliberately an allow-list rather than a block-list: a customer with no
     * consent row at all -- an account from before the opt-in was asked for,
     * say -- is absent from the result and so is not emailed. Consent has to
     * be given, not merely not-refused.
     *
     * @param int[] $customerIds
     *
     * @return array<int, true> keyed by customer id
     */
    public function findCustomerIdsWithConsent(string $type, array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('oc')
            ->select('DISTINCT IDENTITY(u.customer) AS customer_id')
            ->innerJoin('oc.user', 'u')
            ->andWhere('oc.type = :type')
            ->andWhere('oc.accepted = true')
            ->andWhere('oc.withdrawedAt IS NULL')
            ->andWhere('IDENTITY(u.customer) IN (:customerIds)')
            ->setParameter('type', $type)
            ->setParameter('customerIds', $customerIds)
            ->getQuery()
            ->getScalarResult();

        return array_fill_keys(array_map('intval', array_column($rows, 'customer_id')), true);
    }
}
