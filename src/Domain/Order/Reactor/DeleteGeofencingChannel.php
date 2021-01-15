<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use Redis;
use Psr\Log\LoggerInterface;

class DeleteGeofencingChannel
{
    private $tile38;
    private $doorstepChanNamespace;
    private $logger;

    public function __construct(
        Redis $tile38,
        string $doorstepChanNamespace,
        LoggerInterface $logger)
    {
        $this->tile38 = $tile38;
        $this->doorstepChanNamespace = $doorstepChanNamespace;
        $this->logger = $logger;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        $delivery = $order->getDelivery();

        if (null === $delivery) {
            return;
        }

        $dropoff = $delivery->getDropoff();

        $this->tile38->rawCommand(
            'DELCHAN',
            sprintf('%s:dropoff:%d', $this->doorstepChanNamespace, $dropoff->getId())
        );
    }
}
