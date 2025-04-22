<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\RestoreOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class RestoreOrderHandler
{
    private $eventRecorder;
    private $stateMachineFactory;

    public function __construct(
        RecordsMessages $eventRecorder,
        StateMachineFactoryInterface $stateMachineFactory)
    {
        $this->eventRecorder = $eventRecorder;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    public function __invoke(RestoreOrder $command)
    {
        $order = $command->getOrder();

        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

        if (!$stateMachine->can(OrderTransitions::TRANSITION_RESTORE)) {
            throw new \RuntimeException(
                sprintf('Order #%d cannot be restored', $order->getId())
            );
        }

        $this->eventRecorder->record(new Event\OrderRestored($order));
    }
}
