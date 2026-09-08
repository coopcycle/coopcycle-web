<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class SearchQueryRepository extends EntityRepository
{
    /**
     * @return SearchQuery[]
     */
    public function findByUserAndScope(User $user, string $scope): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.scope = :scope')
            ->setParameter('user', $user)
            ->setParameter('scope', $scope)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
