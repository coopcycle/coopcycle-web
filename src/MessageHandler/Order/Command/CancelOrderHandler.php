<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\CancelOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class CancelOrderHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private StateMachineFactoryInterface $stateMachineFactory)
    {}

    public function __invoke(CancelOrder $command)
    {
        $order = $command->getOrder();
        $reason = $command->getReason();

        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

        if (!$stateMachine->can(OrderTransitions::TRANSITION_CANCEL)) {
            throw new OrderNotCancellableException(
                sprintf('Order #%d cannot be cancelled', $order->getId())
            );
        }

        // Cancelling an order for "no show" is only possible for collection
        if (OrderInterface::CANCEL_REASON_NO_SHOW === $reason && $order->getFulfillmentMethod() === 'delivery') {
            throw new OrderNotCancellableException(
                sprintf('Order #%d cannot be cancelled for reason "%s"', $order->getId(), $reason)
            );
        }

        $event = new Event\OrderCancelled($order, $reason);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
