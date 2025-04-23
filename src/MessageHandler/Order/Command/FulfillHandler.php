<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Fulfill;
use AppBundle\Domain\Order\Event;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class FulfillHandler
{
    public function __construct(private MessageBusInterface $eventBus)
    {}

    public function __invoke(Fulfill $command)
    {
        $order = $command->getOrder();

        $event = new Event\OrderFulfilled($order);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
