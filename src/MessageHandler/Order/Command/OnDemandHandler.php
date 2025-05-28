<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\OnDemand;
use AppBundle\Domain\Order\Event;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class OnDemandHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private OrderNumberAssignerInterface $orderNumberAssigner)
    {}

    public function __invoke(OnDemand $command)
    {
        $order = $command->getOrder();

        // TODO Check if the order is actually for OnDemand

        $this->orderNumberAssigner->assignNumber($order);

        $event = new Event\OrderCreated($order);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
