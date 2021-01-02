<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Entity\Delivery;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTextEncoder;
use Carbon\Carbon;

class CreateTasks
{
    private $routing;
    private $orderTextEncoder;

    public function __construct(
        RoutingInterface $routing,
        OrderTextEncoder $orderTextEncoder)
    {
        $this->routing = $routing;
        $this->orderTextEncoder = $orderTextEncoder;
    }

    public function __invoke(OrderAccepted $event)
    {
        $order = $event->getOrder();

        if (null !== $order->getDelivery()) {
            return;
        }

        if ($order->isTakeaway()) {
            return;
        }

        $pickupAddress = $order->getVendor()->getAddress();
        $dropoffAddress = $order->getShippingAddress();

        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $shippingTimeRange = $order->getShippingTimeRange();

        $pickupTime = Carbon::instance($shippingTimeRange->getLower())
            ->average($shippingTimeRange->getUpper())
            ->subSeconds($duration);

        $pickupTimeRange = DateUtils::dateTimeToTsRange($pickupTime, 5);

        $delivery = new Delivery();

        $pickup = $delivery->getPickup();
        $pickup->setAddress($pickupAddress);
        $pickup->setAfter($pickupTimeRange->getLower());
        $pickup->setBefore($pickupTimeRange->getUpper());

        $dropoff = $delivery->getDropoff();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setAfter($shippingTimeRange->getLower());
        $dropoff->setBefore($shippingTimeRange->getUpper());

        $orderAsText = $this->orderTextEncoder->encode($order, 'txt');

        $pickup->setComments($orderAsText);
        $dropoff->setComments($orderAsText);

        $order->setDelivery($delivery);
    }
}
