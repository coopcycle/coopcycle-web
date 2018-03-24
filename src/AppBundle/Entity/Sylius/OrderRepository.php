<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryOrderItem;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;

class OrderRepository extends BaseOrderRepository
{
    public function findOneByDelivery(Delivery $delivery)
    {
        $deliveryOrderItem = $this->getEntityManager()
            ->getRepository(DeliveryOrderItem::class)
            ->findOneByDelivery($delivery);

        if ($deliveryOrderItem) {
            return $deliveryOrderItem->getOrderItem()->getOrder();
        }
    }
}
