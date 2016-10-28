<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use AppBundle\Utils\GeoUtils;
use Doctrine\Common\Persistence\ManagerRegistry;
use Dunglas\ApiBundle\Event\DataEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OrderListener
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage) {
        $this->tokenStorage = $tokenStorage;
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
     * @param DataEvent $event
     */
    public function onPreCreate(DataEvent $event)
    {
        $data = $event->getData();

        if ($data instanceof Order) {
            $customer = $this->getUser();
            $data->setCustomer($customer);
        }
    }

    /**
     * @param DataEvent $event
     */
    public function onPostCreate(DataEvent $event)
    {
        $data = $event->getData();

        $redis = new \Redis();
        if (!$redis->connect('127.0.0.1', 6379)) {
            throw new \Exception('Could not connect to Redis');
        }

        if ($data instanceof Order) {

            $coords = GeoUtils::asGeoCoordinates($data->getRestaurant()->getGeo());

            $redis->geoadd('GeoSet', $coords->getLatitude(), $coords->getLongitude(), 'order:'.$data->getId());

            // $customer = $this->getUser();
            // $data->setCustomer($customer);
        }
    }
}