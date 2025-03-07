<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\RestoreOrder;
use AppBundle\Domain\Order\Event;
// use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use SimpleBus\Message\Recorder\RecordsMessages;

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
