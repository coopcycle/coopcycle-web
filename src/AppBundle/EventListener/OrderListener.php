<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderEvent;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OrderListener
{
    private $tokenStorage;
    private $deliveryServiceFactory;

    public function __construct(TokenStorageInterface $tokenStorage, DeliveryServiceFactory $deliveryServiceFactory)
    {
        $this->tokenStorage = $tokenStorage;
        $this->deliveryServiceFactory = $deliveryServiceFactory;
    }

    private function getDeliveryService(Order $order)
    {
        return $this->deliveryServiceFactory->createForRestaurant($order->getRestaurant());
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

        if ($entity instanceof Order) {

            $order = $entity;

            if (null === $entity->getCustomer()) {
                $order->setCustomer($this->getUser());
            }

            $delivery = $order->getDelivery();

            // Make sure models are associated
            $delivery->setOrder($order);

            // FIXME Date should be mandatory
            if (null === $delivery->getDate()) {
                // FIXME Make sure the restaurant is opened
                $delivery->setDate(new \DateTime('+30 minutes'));
            }

            if (null === $delivery->getOriginAddress()) {
                $delivery->setOriginAddress($entity->getRestaurant()->getAddress());
            }

            if (!$delivery->isCalculated()) {
                $this->getDeliveryService($order)->calculate($delivery);
            }
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

            $this->getDeliveryService($order)->onOrderUpdate($order);
        }
    }

    public function onPaymentSuccess(Event $event)
    {
        $order = $event->getSubject();

        $this->getDeliveryService($order)->create($order);
    }
}
