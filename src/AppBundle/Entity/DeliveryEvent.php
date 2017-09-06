<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class DeliveryEvent
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $eventName;

    /**
     * @ORM\ManyToOne(targetEntity="Delivery", inversedBy="events")
     */
    private $delivery;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $courier;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct(Delivery $delivery, $eventName, ApiUser $courier = null)
    {
        $this->delivery = $delivery;
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
