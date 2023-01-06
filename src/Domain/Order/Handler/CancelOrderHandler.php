<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CancelOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Service\StripeManager;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use SimpleBus\Message\Recorder\RecordsMessages;

class CancelOrderHandler
{
    private $stripeManager;
    private $eventRecorder;
    private $stateMachineFactory;

    public function __construct(
        StripeManager $stripeManager,
        RecordsMessages $eventRecorder,
        StateMachineFactoryInterface $stateMachineFactory)
    {
        $this->stripeManager = $stripeManager;
        $this->eventRecorder = $eventRecorder;
        $this->stateMachineFactory = $stateMachineFactory;
    }

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

        $completedPayment = $order->getLastPayment(PaymentInterface::STATE_COMPLETED);
        if (null !== $completedPayment && $completedPayment->isGiropay()) {
            $this->stripeManager->refund($completedPayment, null, true);
        }

        $this->eventRecorder->record(new Event\OrderCancelled($order, $reason));
    }
}
