<?php

namespace AppBundle\Entity\Sylius;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository as BaseTaxonRepository;

class TaxonRepository extends BaseTaxonRepository
{
    private $nestedTreeRepository;

    public function __construct(EntityManagerInterface $em, Mapping\ClassMetadata $class)
    {
        parent::__construct($em, $class);

        // TODO Remove in constructor
        $this->nestedTreeRepository = new NestedTreeRepository($em, $class);
    }

    public function reorder($node, $sortByField = null, $direction = 'ASC', $verify = true)
    {
        return $this->nestedTreeRepository->reorder($node, $sortByField, $direction, $verify);
    }

    public function getNestedTreeRepository()
    {
        return $this->nestedTreeRepository;
    }
}
