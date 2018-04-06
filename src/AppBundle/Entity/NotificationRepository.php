<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Notification;
use Doctrine\ORM\EntityRepository;

class NotificationRepository extends EntityRepository
{
    private function createQueryBuilderForUser(ApiUser $user)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user);
    }

    public function markAsRead(ApiUser $user, array $ids)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->update(Notification::class, 'n')
            ->set('n.read', ':read')
            ->andWhere('n.user = :user')
            ->andWhere('n.id in (:ids)')
            ->setParameter('user', $user)
            ->setParameter('read', true)
            ->setParameter('ids', $ids);

        $qb->getQuery()->execute();
    }

    public function markAllAsRead(ApiUser $user)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->update(Notification::class, 'n')
            ->set('n.read', ':new_value')
            ->andWhere('n.user = :user')
            ->andWhere('n.read = :current_value')
            ->setParameter('user', $user)
            ->setParameter('new_value', true)
            ->setParameter('current_value', false);

        $qb->getQuery()->execute();
    }

    public function countByUser(ApiUser $user)
    {
        $qb = $this
            ->createQueryBuilderForUser($user)
            ->select('COUNT(n)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countUnreadByUser(ApiUser $user)
    {
        $qb = $this
            ->createQueryBuilderForUser($user)
            ->select('COUNT(n)')
            ->andWhere('n.read = :read')
            ->setParameter('read', false);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByUser(ApiUser $user, $maxResults = null)
    {
        $qb = $this
            ->createQueryBuilderForUser($user)
            ->orderBy('n.createdAt', 'DESC');

        if ($maxResults) {
            $qb->setMaxResults($maxResults);
        }

        return $qb->getQuery()->getResult();
    }
}
