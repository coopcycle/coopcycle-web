<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class OrderEvent
{
    const STATUS_WAITING = 'WAITING';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_READY = 'READY';
    const STATUS_PICKED = 'PICKED';
    const STATUS_ACCIDENT = 'ACCIDENT';
    const STATUS_DELIVERED = 'DELIVERED';

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $eventName;

    /**
     * @ORM\ManyToOne(targetEntity="Order", inversedBy="events")
     */
    private $order;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $courier;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct(Order $order, $eventName, ApiUser $courier = null) {
        $this->order = $order;
        $this->eventName = $eventName;
        $this->courier = $courier;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOrder()
    {
        return $this->customer;
    }

    public function getEventName()
    {
        return $this->eventName;
    }

    public function getCourier()
    {
        return $this->courier;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
