<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\DelayOrder;
use AppBundle\Domain\Order\Event;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class DelayOrderHandler
{

    public function __construct(private MessageBusInterface $eventBus)
    {}

    public function __invoke(DelayOrder $command)
    {
        $order = $command->getOrder();

        $event = new Event\OrderDelayed($order, $command->getDelay());
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
