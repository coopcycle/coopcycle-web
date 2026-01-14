<?php

namespace AppBundle\Entity\Sylius;

use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository as BaseTaxonRepository;

class TaxonRepository extends BaseTaxonRepository
{
    private $nestedTreeRepository;

    public function reorder($node, $sortByField = null, $direction = 'ASC', $verify = true)
    {
        return $this->getNestedTreeRepository()->reorder($node, $sortByField, $direction, $verify);
    }

    public function getNestedTreeRepository()
    {
        if (null === $this->nestedTreeRepository) {
            $this->nestedTreeRepository = new NestedTreeRepository($this->_em, $this->_class);
        }

        return $this->nestedTreeRepository;
    }
}
