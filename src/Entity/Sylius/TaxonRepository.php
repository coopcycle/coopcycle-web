<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Sylius\ProductTaxon;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Sylius\Component\Product\Model\ProductInterface;
// use Sylius\Component\Taxonomy\Model\TaxonInterface;
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

    public function getProductTaxon(ProductInterface $product): ?ProductTaxon
    {
        $qb = $this->getEntityManager()->getRepository(ProductTaxon::class)->createQueryBuilder('pt')
            ->andWhere('pt.product = :product')
            ->setParameter('product', $product);

        return $qb->getQuery()->getOneOrNullResult();

        // if ($taxonProduct) {
        //     return $taxonProduct->getTaxon();
        // }

        // return null;
    }
}
