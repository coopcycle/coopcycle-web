<?php

namespace AppBundle\Event;

use AppBundle\Entity\Delivery;
use Symfony\Component\EventDispatcher\Event;

class DeliveryCreateEvent extends Event
{
    const NAME = 'delivery.create';

    protected $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }
}

