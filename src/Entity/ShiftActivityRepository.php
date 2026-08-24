<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class ShiftActivityRepository extends EntityRepository
{
    public function findOneBySlug(string $slug): ?ShiftActivity
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
