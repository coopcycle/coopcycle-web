<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderEvent;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Predis\Client as Redis;

class OrderListener
{
    private $tokenStorage;
    private $redis;

    public function __construct(TokenStorageInterface $tokenStorage, Redis $redis) {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
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
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Order && null === $entity->getCustomer()) {
            $customer = $this->getUser();
            $entity->setCustomer($customer);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {

    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $order = $args->getObject();
        $em = $args->getEntityManager();

        if ($order instanceof Order) {
            $orderEvent = new OrderEvent($order, $order->getStatus(), $order->getCourier());
            $em->persist($orderEvent);
            $em->flush();

            $this->redis->publish('order_events', json_encode([
                'order' => $order->getId(),
                'courier' => null !== $order->getCourier() ? $order->getCourier()->getId() : null,
                'status' => $order->getStatus(),
                'timestamp' => $orderEvent->getCreatedAt()->getTimestamp(),
            ]));
        }
    }

    public function onPaymentSuccess(Event $event)
    {
        $order = $event->getSubject();

        $restaurant = $order->getRestaurant();
        $deliveryAddress = $order->getDeliveryAddress();

        $this->redis->geoadd(
            'orders:geo',
            $restaurant->getGeo()->getLongitude(),
            $restaurant->getGeo()->getLatitude(),
            'order:'.$order->getId()
        );

        $this->redis->geoadd(
            'restaurants:geo',
            $restaurant->getGeo()->getLongitude(),
            $restaurant->getGeo()->getLatitude(),
            'order:'.$order->getId()
        );
        $this->redis->geoadd(
            'delivery_addresses:geo',
            $deliveryAddress->getGeo()->getLongitude(),
            $deliveryAddress->getGeo()->getLatitude(),
            'order:'.$order->getId()
        );

        $this->redis->lpush(
            'orders:waiting',
            $order->getId()
        );
    }
}