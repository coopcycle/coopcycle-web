<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use AppBundle\Utils\GeoUtils;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OrderListener
{
    private $tokenStorage;
    private $redis;

    public function __construct(TokenStorageInterface $tokenStorage, \Redis $redis) {
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
            $coords = GeoUtils::asGeoCoordinates($entity->getRestaurant()->getGeo());
            $this->redis->geoadd('GeoSet', $coords->getLatitude(), $coords->getLongitude(), 'order:'.$entity->getId());
        }
    }
}