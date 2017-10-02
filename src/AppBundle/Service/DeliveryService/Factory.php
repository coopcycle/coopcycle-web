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
        $deliveryService = $restaurant->getDeliveryService();

        if (null !== $deliveryService && isset($this->services[$deliveryService->getType()])) {
            $object = $this->services[$deliveryService->getType()];

            return $object;
        }

        return $this->fallback;
    }
}
