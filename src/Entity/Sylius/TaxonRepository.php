<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\Taxon;
use Doctrine\ORM\Query\Expr;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
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

    public function getProductTaxon(ProductInterface $product, Taxon $menu): ?ProductTaxon
    {
        if (!$menu->isRoot()) {
            throw new \LogicException('The provided Taxon is not a root taxon.');
        }

        $qb = $this->getEntityManager()->getRepository(ProductTaxon::class)->createQueryBuilder('pt')
            ->join(Taxon::class, 't', Expr\Join::WITH, 'pt.taxon = t.id')
            ->andWhere('pt.product = :product')
            ->andWhere('t.root = :root')
            ->setParameter('product', $product)
            ->setParameter('root', $menu);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getRestaurantForMenu(TaxonInterface $menu): ?LocalBusiness
    {
        $qb = $this->getEntityManager()->getRepository(LocalBusiness::class)->createQueryBuilder('o')
            ->innerJoin('o.taxons', 't')
            ->andWhere('t.id = :id')
            ->setParameter('id', $menu)
            ;

        return $qb->getQuery()->getOneOrNullResult();
    }
}
