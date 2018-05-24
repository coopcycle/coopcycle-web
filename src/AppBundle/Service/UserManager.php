<?php

namespace AppBundle\Service;

use FOS\UserBundle\Doctrine\UserManager as BaseUserManager;

class UserManager extends BaseUserManager
{
    public function searchUsers($q, $limit = 5)
    {
        $qb = $this->getRepository()->createQueryBuilder('u');
        $qb
            ->where('LOWER(u.username) LIKE :q OR LOWER(u.email) LIKE :q')
            ->setParameter('q', '%' . strtolower($q) . '%')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
