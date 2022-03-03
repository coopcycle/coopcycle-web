<?php

namespace AppBundle\Form\Type\QueryBuilder;

use Doctrine\ORM\EntityRepository;

class OrderByNameQueryBuilder
{
    public function __invoke(EntityRepository $er)
    {
        return $er->createQueryBuilder('o')->orderBy('o.name', 'ASC');
    }
}
