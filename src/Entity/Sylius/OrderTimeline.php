<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\DataType\TsRange;
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

    public static function create(OrderInterface $order, TsRange $range, string $preparationTime, ?string $shippingTime = null)
    {
        $timeline = new self();

        $timeline->setPreparationTime($preparationTime);
        if (!empty($shippingTime)) {
            $timeline->setShippingTime($shippingTime);
        }

        if ('collection' === $order->getFulfillmentMethod()) {

            $preparation = clone $range->getMidPoint();
            $preparation->sub(date_interval_create_from_date_string($preparationTime));
            $timeline->setPreparationExpectedAt($preparation);

            $timeline->setPickupExpectedAt($range->getMidPoint());
        } else {

            $dropoff = $range->getMidPoint();
            $timeline->setDropoffExpectedAt($dropoff);

            // The pickup time is when the messenger grabs the bag
            $pickup = clone $dropoff;
            $pickup->sub(date_interval_create_from_date_string($shippingTime));

            // Substract 5 additional minutes to say goodbye, unlock the bike...
            $pickup->sub(date_interval_create_from_date_string('5 minutes'));

            $timeline->setPickupExpectedAt($pickup);

            $preparation = clone $pickup;
            $preparation->sub(date_interval_create_from_date_string($preparationTime));

            $timeline->setPreparationExpectedAt($preparation);
        }

        return $timeline;
    }
}
