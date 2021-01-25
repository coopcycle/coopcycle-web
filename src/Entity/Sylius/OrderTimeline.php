<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;
use Gedmo\Timestampable\Traits\Timestampable;

class OrderTimeline
{
    use Timestampable;

    protected $id;

    protected $order;

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

    /**
     * @var string
     */
    protected $preparationTime;

    /**
     * @var string|null
     */
    protected $shippingTime;

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

    public function setDropoffExpectedAt(?\DateTime $dropoffExpectedAt)
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

    /**
     * @return string
     */
    public function getPreparationTime()
    {
        return $this->preparationTime;
    }

    /**
     * @param string $preparationTime
     *
     * @return self
     */
    public function setPreparationTime($preparationTime)
    {
        $this->preparationTime = $preparationTime;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getShippingTime()
    {
        return $this->shippingTime;
    }

    /**
     * @param string $shippingTime
     *
     * @return self
     */
    public function setShippingTime($shippingTime)
    {
        $this->shippingTime = $shippingTime;

        return $this;
    }
}
