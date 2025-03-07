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
