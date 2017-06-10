<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\DeliveryServiceInterface;

class Factory
{
    private $services = [];
    private $fallback;

    public function __construct(array $services, DeliveryServiceInterface $fallback)
    {
        foreach ($services as $service) {
            $this->services[$service->getKey()] = $service;
        }
        $this->fallback = $fallback;
    }

    public function getServices()
    {
        return $this->services;
    }

    public function createForRestaurant(Restaurant $restaurant)
    {
        $key = $restaurant->getDeliveryService();

        if (!empty($key) && isset($this->services[$key])) {

            return $this->services[$key];
        }

        return $this->fallback;
    }
}
