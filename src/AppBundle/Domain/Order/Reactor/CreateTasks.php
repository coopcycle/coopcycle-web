<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Entity\Delivery;
use AppBundle\Service\RoutingInterface;

class CreateTasks
{
    private $routing;

    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    public function __invoke(OrderAccepted $event)
    {
        $order = $event->getOrder();

        if (null !== $order->getDelivery()) {
            return;
        }

        $pickupAddress = $order->getRestaurant()->getAddress();
        $dropoffAddress = $order->getShippingAddress();

        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $dropoffDoneBefore = $order->getShippedAt();

        $pickupDoneBefore = clone $dropoffDoneBefore;
        $pickupDoneBefore->modify(sprintf('-%d seconds', $duration));

        $delivery = new Delivery();

        $pickup = $delivery->getPickup();
        $pickup->setAddress($pickupAddress);
        $pickup->setDoneBefore($pickupDoneBefore);

        $dropoff = $delivery->getDropoff();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setDoneBefore($dropoffDoneBefore);

        $order->setDelivery($delivery);
    }
}
