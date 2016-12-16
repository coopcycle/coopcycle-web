<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Event\LifecycleEventArgs;
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

        if ($entity instanceof Order) {
            $customer = $this->getUser();
            $entity->setCustomer($customer);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Order) {

            $restaurant = $entity->getRestaurant();
            $deliveryAddress = $entity->getDeliveryAddress();

            $this->redis->geoadd(
                'orders:geo',
                $restaurant->getGeo()->getLongitude(),
                $restaurant->getGeo()->getLatitude(),
                'order:'.$entity->getId()
            );

            $this->redis->geoadd(
                'restaurants:geo',
                $restaurant->getGeo()->getLongitude(),
                $restaurant->getGeo()->getLatitude(),
                'order:'.$entity->getId()
            );
            $this->redis->geoadd(
                'delivery_addresses:geo',
                $deliveryAddress->getGeo()->getLongitude(),
                $deliveryAddress->getGeo()->getLatitude(),
                'order:'.$entity->getId()
            );

            $this->redis->lpush(
                'orders:waiting',
                $entity->getId()
            );
        }
    }
}