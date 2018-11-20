<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderItemRepository as BaseOrderItemRepository;
use Sylius\Component\Product\Model\ProductInterface;

class OrderItemRepository extends BaseOrderItemRepository
{
    public function findCartItemsByProduct(ProductInterface $product)
    {
        $qb = $this->createQueryBuilder('i')
            ->innerJoin('i.order', 'o')
            ->innerJoin('i.variant', 'v')
            ->andWhere('o.state = :state')
            ->andWhere('v.product = :product')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->setParameter('product', $product)
        ;

        return $qb->getQuery()->getResult();
    }
}
