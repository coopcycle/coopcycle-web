<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionInterface;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;

class ProductRepository extends BaseProductRepository
{

    public function findByOption(ProductOptionInterface $productOption)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->innerJoin('p.options', 'o');
        $qb->where('o.option = :option');
        $qb->setParameter('option', $productOption);

        return $qb->getQuery()->getResult();
    }

    public function findOnDemandDeliveryProduct(): Product
    {
        return $this->findOneBy(['code' => 'CPCCL-ODDLVR']);
    }
}
