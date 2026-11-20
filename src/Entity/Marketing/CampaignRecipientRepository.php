<?php

namespace AppBundle\Entity\Marketing;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class CampaignRecipientRepository extends EntityRepository
{
    /**
     * Of the addresses given, those already sent a campaign since $since.
     *
     * This is the frequency cap. Only actual sends count -- an address left
     * out of the last campaign because it was suppressed or capped hasn't
     * been bothered, so it shouldn't be held back again.
     *
     * @param string[] $emails
     *
     * @return array<string, true> keyed by lowercased address
     */
    public function findRecentlyEmailed(array $emails, \DateTime $since): array
    {
        if (empty($emails)) {
            return [];
        }

        $normalized = array_values(array_unique(array_map(
            fn (string $email) => mb_strtolower(trim($email)),
            $emails
        )));

        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.email')
            ->andWhere('r.email IN (:emails)')
            ->andWhere('r.status = :sent')
            ->andWhere('r.sentAt IS NOT NULL')
            ->andWhere('r.sentAt >= :since')
            ->setParameter('emails', $normalized)
            ->setParameter('sent', CampaignRecipient::STATUS_SENT)
            ->setParameter('since', $since)
            ->getQuery()
            ->getScalarResult();

        return array_fill_keys(array_column($rows, 'email'), true);
    }

    public function countByCampaignAndStatus(Campaign $campaign): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.status AS status, COUNT(r.id) AS total')
            ->andWhere('r.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->groupBy('r.status')
            ->getQuery()
            ->getArrayResult();

        return array_combine(
            array_column($rows, 'status'),
            array_map('intval', array_column($rows, 'total'))
        );
    }

    public function createListQueryBuilder(Campaign $campaign): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->orderBy('r.id', 'ASC');
    }
}
