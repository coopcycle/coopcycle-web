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
        return !empty($this->findInboundReferences([$reference], $transporter));
    }

    /**
     * The references already imported for this transporter, among the given ones:
     * one query for all the tasks of a file.
     * @param array<string> $references
     * @return array<string>
     */
    public function findInboundReferences(array $references, string $transporter): array
    {
        if (empty($references)) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.reference')
            ->where('e.reference IN (:references)')
            ->andWhere('e.transporter = :transporter')
            ->andWhere('e.direction = :direction')
            ->setParameter('references', array_values(array_unique($references)))
            ->setParameter('transporter', $transporter)
            ->setParameter('direction', EDIFACTMessage::DIRECTION_INBOUND)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getUnsynced(string $transporter): mixed
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.syncedAt IS NULL')
            ->andWhere('e.direction = :direction')
            ->andWhere('e.transporter = :transporter')
            ->setParameter('direction', EDIFACTMessage::DIRECTION_OUTBOUND)
            ->setParameter('transporter', $transporter)
            // POD|CFM confirms the LIV|CFM it follows, so the events of a
            // shipment must reach the transporter in the order they happened.
            ->orderBy('e.id', 'ASC');


        return $qb->getQuery()->getResult();
    }
}
