<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class RruleRepository extends EntityRepository
{
    public function findRulesForDate(\DateTime $date) {
        return $this->createQueryBuilder('r')
            ->andWhere('DATE(r.end) > :date OR r.end is null')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

}