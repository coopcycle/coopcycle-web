<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\DeliveryEvent;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Predis\Client as Redis;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryListener
{
    private $redis;
    private $normalizer;

    public function __construct(Redis $redis, NormalizerInterface $normalizer)
    {
        $this->redis = $redis;
        $this->normalizer = $normalizer;
    }

    public function prePersist(Delivery $delivery, LifecycleEventArgs $args)
    {
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        // Make sure legacy "originAddress" & "deliveryAddress" are set
        if (null === $delivery->getOriginAddress() && null !== $pickup) {
            $delivery->setOriginAddress($pickup->getAddress());
        }
        if (null === $delivery->getDeliveryAddress() && null !== $dropoff) {
            $delivery->setDeliveryAddress($dropoff->getAddress());
        }

        // Make sure tasks are linked
        if (null !== $pickup && null !== $dropoff && !$pickup->hasPrevious()) {
            $dropoff->setPrevious($pickup);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(Delivery $delivery, LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();

        $deliveryEvent = new DeliveryEvent($delivery, $delivery->getStatus(), $delivery->getCourier());
        $em->persist($deliveryEvent);
        $em->flush();

        $this->redis->publish('delivery_events', json_encode([
            'delivery' => $this->normalizer->normalize($delivery, 'jsonld', [
                'resource_class' => Delivery::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['delivery', 'place']
            ]),
            'courier' => $delivery->getCourier() !== null ? $delivery->getCourier()->getId() : null,
            'status' => $delivery->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));
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
