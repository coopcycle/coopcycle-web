<?php

namespace AppBundle\Entity\Edifact;

use Doctrine\ORM\EntityRepository;
/**
 * @extends EntityRepository<object>
 */
class EDIFACTMessageRepository extends EntityRepository {

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
        ->execute();
    }

    public function getUnsynced(): mixed
    {
        $qb = $this->createQueryBuilder('e')
        ->where('e.syncedAt IS NULL')
        ->andWhere('e.direction = :direction')
        ->setParameter('direction', EDIFACTMessage::DIRECTION_OUTBOUND);

        return $qb->getQuery()->getResult();
    }
}
