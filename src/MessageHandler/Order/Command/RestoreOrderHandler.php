<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\RestoreOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class RestoreOrderHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private StateMachineFactoryInterface $stateMachineFactory)
    {}

    public function __invoke(RestoreOrder $command)
    {
        $order = $command->getOrder();

        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

        if (!$stateMachine->can(OrderTransitions::TRANSITION_RESTORE)) {
            throw new \RuntimeException(
                sprintf('Order #%d cannot be restored', $order->getId())
            );
        }

        $event = new Event\OrderRestored($order);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
