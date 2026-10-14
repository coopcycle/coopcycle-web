<?php

namespace AppBundle\Entity\Marketing;

use Doctrine\ORM\EntityRepository;

class EmailSuppressionRepository extends EntityRepository
{
    public function findOneByEmailAndStream(string $email, string $messageStream): ?EmailSuppression
    {
        return $this->findOneBy([
            'email' => mb_strtolower(trim($email)),
            'messageStream' => $messageStream,
        ]);
    }

    /**
     * Of the addresses given, the ones that must not be emailed -- matched
     * across every stream, so an address that hard-bounced anywhere is left
     * out of a campaign rather than being sent to and dropped.
     *
     * Returns a set keyed by the lowercased address, so callers can look up
     * membership directly instead of scanning.
     *
     * @param string[] $emails
     *
     * @return array<string, true>
     */
    public function findSuppressed(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        $normalized = array_values(array_unique(array_map(
            fn (string $email) => mb_strtolower(trim($email)),
            $emails
        )));

        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT s.email')
            ->andWhere('s.email IN (:emails)')
            ->setParameter('emails', $normalized)
            ->getQuery()
            ->getScalarResult();

        return array_fill_keys(array_column($rows, 'email'), true);
    }

    public function isSuppressed(string $email): bool
    {
        return [] !== $this->findSuppressed([$email]);
    }
}
