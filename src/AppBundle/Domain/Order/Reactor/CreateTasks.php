<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\OrderTextEncoder;
use SimpleBus\Message\Bus\MessageBus;

class CreateTasks
{
    private $routing;
    private $orderTextEncoder;
    private $eventBus;

    public function __construct(
        RoutingInterface $routing,
        OrderTextEncoder $orderTextEncoder,
        MessageBus $eventBus)
    {
        $this->routing = $routing;
        $this->orderTextEncoder = $orderTextEncoder;
        $this->eventBus = $eventBus;
    }

    public function __invoke(OrderAccepted $event)
    {
        $order = $event->getOrder();

        if (null !== $order->getDelivery()) {

            foreach ($order->getDelivery()->getTasks() as $task) {
                if ($task->isVirtual()) {
                    $task->setStatus(Task::STATUS_TODO);
                    $this->eventBus->handle(new TaskCreated($task));
                }
            }

            return;
        }

        if (null !== $order->getRestaurant()) {
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

            $orderAsText = $this->orderTextEncoder->encode($order, 'txt');

            $pickup->setComments($orderAsText);
            $dropoff->setComments($orderAsText);

            $order->setDelivery($delivery);
        }
    }
}
