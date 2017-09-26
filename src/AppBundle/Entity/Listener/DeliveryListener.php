<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryEvent;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Predis\Client as Redis;

class DeliveryListener
{
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(Delivery $delivery, LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();

        $deliveryEvent = new DeliveryEvent($delivery, $delivery->getStatus(), $delivery->getCourier());
        $em->persist($deliveryEvent);
        $em->flush();

        $this->redis->publish('delivery_events', json_encode([
            'delivery' => $delivery->getId(),
            'courier' => $delivery->getCourier() !== null ? $delivery->getCourier()->getId() : null,
            'status' => $delivery->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));
    }
}
