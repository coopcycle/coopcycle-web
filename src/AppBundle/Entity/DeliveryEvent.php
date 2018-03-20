<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class DeliveryEvent
{
    private $id;

    private $eventName;

    private $delivery;

    private $courier;

    private $createdAt;

    public function __construct(Delivery $delivery, $eventName, ApiUser $courier = null)
    {
        $this->delivery = $delivery;
        $delivery->getEvents()->add($this);
        $this->eventName = $eventName;
        $this->courier = $courier;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function getCourier()
    {
        return $this->courier;
    }

    public function getEventName()
    {
        return $this->eventName;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
