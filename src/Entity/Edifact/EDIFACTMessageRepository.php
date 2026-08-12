<?php

namespace AppBundle\Entity\Edifact;

use Doctrine\ORM\EntityRepository;
/**
 * @extends EntityRepository<object>
 */
class EDIFACTMessageRepository extends EntityRepository {
    /**
     * @param array<int> $ids
     */
    public function setSynced(array $ids, string $filename): void
    {
        $qb = $this->createQueryBuilder('e');
        $qb
        ->update()
        ->set('e.syncedAt', ':now')
        ->set('e.edifactFile', ':file')
        ->setParameter('now', new \DateTime())
        ->setParameter('file', $filename)
        ->where($qb->expr()->in('e.id', $ids))
        ->getQuery()
        ->getSingleScalarResult();
    }

    /**
     * Whether an inbound message for this reference has already been imported
     * for this transporter. Used to keep the import idempotent so a file that
     * was processed but not acknowledged (e.g. after a mid-batch failure) is
     * not imported twice on the next run.
     */
    public function hasInbound(string $reference, string $transporter): bool
    {
        $count = (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.reference = :reference')
            ->andWhere('e.transporter = :transporter')
            ->andWhere('e.direction = :direction')
            ->setParameter('reference', $reference)
            ->setParameter('transporter', $transporter)
            ->setParameter('direction', EDIFACTMessage::DIRECTION_INBOUND)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function getUnsynced(string $transporter): mixed
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.syncedAt IS NULL')
            ->andWhere('e.direction = :direction')
            ->andWhere('e.transporter = :transporter')
            ->setParameter('direction', EDIFACTMessage::DIRECTION_OUTBOUND)
            ->setParameter('transporter', $transporter);


        return $qb->getQuery()->getResult();
    }
}
