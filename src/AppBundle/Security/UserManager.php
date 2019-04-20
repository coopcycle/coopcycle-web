<?php

namespace AppBundle\Security;

use FOS\UserBundle\Doctrine\UserManager as BaseUserManager;

class UserManager extends BaseUserManager
{
    /**
     * @param string $role
     * @return array
     */
    public function findUsersByRole($role)
    {
        $qb = $this->getRepository()
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :roles')
            ->setParameter('roles', sprintf('%%%s%%', $role));

        return $qb->getQuery()->getResult();
    }
}
