<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;

class OrderTimeline
{
    protected $id;

    protected $order;

    protected $createdAt;

    protected $updatedAt;

    /**
     * The time the order is expected to be dropped.
     */
    protected $dropoffExpectedAt;

    /**
     * The time the order is expected to be picked up.
     */
    protected $pickupExpectedAt;

    /**
     * The time the order preparation should start.
     */
    protected $preparationExpectedAt;

    protected $dropoffAt;

    protected $pickupAt;

    protected $readyAt;

    public function getId()
    {
        return $this->id;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getDropoffExpectedAt()
    {
        return $this->dropoffExpectedAt;
    }

    public function setDropoffExpectedAt(\DateTime $dropoffExpectedAt)
    {
        $this->dropoffExpectedAt = $dropoffExpectedAt;

        return $this;
    }

    public function getPickupExpectedAt()
    {
        return $this->pickupExpectedAt;
    }

    public function setPickupExpectedAt(\DateTime $pickupExpectedAt)
    {
        $this->pickupExpectedAt = $pickupExpectedAt;

        return $this;
    }

    public function getPreparationExpectedAt()
    {
        return $this->preparationExpectedAt;
    }

    public function setPreparationExpectedAt(\DateTime $preparationExpectedAt)
    {
        $this->preparationExpectedAt = $preparationExpectedAt;

        return $this;
    }

    public function getPickupAt()
    {
        return $this->pickupAt;
    }

    public function setPickupAt(\DateTime $pickupAt)
    {
        $this->pickupAt = $pickupAt;

        return $this;
    }

    public function getDropoffAt()
    {
        return $this->dropoffAt;
    }

    public function setDropoffAt(\DateTime $dropoffAt)
    {
        $this->dropoffAt = $dropoffAt;

        return $this;
    }
}
