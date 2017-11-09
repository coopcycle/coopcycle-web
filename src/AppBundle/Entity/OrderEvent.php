<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class OrderEvent
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
     * @ORM\ManyToOne(targetEntity="Order", inversedBy="events")
     */
    private $order;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
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
