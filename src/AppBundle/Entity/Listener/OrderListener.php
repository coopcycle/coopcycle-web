<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderEvent;
use AppBundle\Event\OrderCreateEvent;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;

class OrderListener
{
    private $tokenStorage;
    private $redis;
    private $serializer;
    private $orderManager;
    private $eventDispatcher;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        Redis $redis,
        SerializerInterface $serializer,
        OrderManager $orderManager,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->serializer = $serializer;
        $this->orderManager = $orderManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(Order $order, LifecycleEventArgs $args)
    {
        $delivery = $order->getDelivery();

        // Make sure customer is set
        if (null === $order->getCustomer()) {
            $order->setCustomer($this->getUser());
        }

        // Make sure models are associated
        $delivery->setOrder($order);

        // Make sure originAddress is set
        if (null === $delivery->getOriginAddress()) {
            $delivery->setOriginAddress($order->getRestaurant()->getAddress());
        }

        // HACK: set manually order_item_id on order_item_modifier
        // hopefully this get fixed : https://github.com/api-platform/api-platform/issues/430
        $em = $args->getEntityManager();
        foreach ($order->getOrderedItem() as $item) {
            foreach ($item->getModifiers() as $modifier) {
                $modifier->setOrderItem($item);
                $em->persist($modifier);
            }
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(Order $order, LifecycleEventArgs $args)
    {
        $deliveryId = $order->getDelivery() ? $order->getDelivery()->getId() : null;

        $this->redis->publish('order_events', json_encode([
            'delivery' => $deliveryId,
            'order' => $order->getId(),
            'status' => $order->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));

        $this->eventDispatcher->dispatch(OrderCreateEvent::NAME, new OrderCreateEvent($order));
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(Order $order, LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();

        $orderEvent = new OrderEvent($order, $order->getStatus());
        $em->persist($orderEvent);
        $em->flush();

        $deliveryId = $order->getDelivery() ? $order->getDelivery()->getId() : null;

        $this->redis->publish('order_events', json_encode([
            'delivery' => $deliveryId,
            'order' => $order->getId(),
            'status' => $order->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));
    }
}
