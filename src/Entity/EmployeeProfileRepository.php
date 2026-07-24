<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class EmployeeProfileRepository extends EntityRepository
{
    public function findOneByUser(User $user): ?EmployeeProfile
    {
        return $this->findOneBy(['user' => $user]);
    }
}
