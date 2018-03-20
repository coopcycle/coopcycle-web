<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class OrderEvent
{
    private $id;

    private $eventName;

    private $order;

    private $createdAt;

    public function __construct(Order $order, $eventName)
    {
        $this->order = $order;
        $order->getEvents()->add($this);
        $this->eventName = $eventName;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOrder()
    {
        return $this->order;
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
