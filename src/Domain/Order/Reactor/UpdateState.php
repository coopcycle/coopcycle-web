<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Command\AcceptOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use AppBundle\Utils\OrderTimeHelper;
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * This Reactor is responsible for updating the state of the aggregate.
 */
class UpdateState
{
    private $stateMachineFactory;
    private $orderProcessor;
    private $eventBus;

    private $eventNameToTransition = [];

    public function __construct(
        StateMachineFactoryInterface $stateMachineFactory,
        OrderProcessorInterface $orderProcessor,
        MessageBus $eventBus,
        OrderTimeHelper $orderTimeHelper)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderProcessor = $orderProcessor;
        $this->eventBus = $eventBus;
        $this->orderTimeHelper = $orderTimeHelper;

        $this->eventNameToTransition = [
            Event\OrderCreated::messageName()   => OrderTransitions::TRANSITION_CREATE,
            Event\OrderAccepted::messageName()  => OrderTransitions::TRANSITION_ACCEPT,
            Event\OrderRefused::messageName()   => OrderTransitions::TRANSITION_REFUSE,
            Event\OrderCancelled::messageName() => OrderTransitions::TRANSITION_CANCEL,
            Event\OrderFulfilled::messageName() => OrderTransitions::TRANSITION_FULFILL,
        ];
    }

    public function __invoke(Event $event)
    {
        if ($event instanceof Event\CheckoutSucceeded || $event instanceof Event\CheckoutFailed) {
            $this->handleCheckoutEvent($event);
            return;
        }

        $this->handleStateChange($event);

        $order = $event->getOrder();

        if ($event instanceof Event\OrderCreated) {
            foreach ($order->getPayments() as $payment) {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                if ($stateMachine->can(PaymentTransitions::TRANSITION_CREATE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE);
                }
            }
        }

        if ($event instanceof Event\OrderFulfilled) {
            foreach ($order->getPayments() as $payment) {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                }
            }
        }

        if ($event instanceof Event\OrderCancelled) {

            $transition = $event->getReason() === OrderInterface::CANCEL_REASON_NO_SHOW ?
                PaymentTransitions::TRANSITION_COMPLETE : PaymentTransitions::TRANSITION_CANCEL;

            foreach ($order->getPayments() as $payment) {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                if ($stateMachine->can($transition)) {
                    $stateMachine->apply($transition);
                }
            }
        }
    }

    private function handleCheckoutEvent(Event $event)
    {
        if ($event instanceof Event\CheckoutSucceeded) {

            $order = $event->getOrder();
            $payment = $event->getPayment();

            // FIXME
            // We shouldn't auto-assign a date when it is a quote
            // Keeping this until it is possible to choose an arbitrary date
            // https://github.com/coopcycle/coopcycle-web/issues/698

            if (null === $order->getShippingTimeRange()) {
                $order->setShippingTimeRange(
                    $this->orderTimeHelper->getShippingTimeRange($order)
                );
            }

            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

            if (null !== $payment) {
                if ($stateMachine->can(PaymentTransitions::TRANSITION_AUTHORIZE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_AUTHORIZE);
                } elseif ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                }
            }

            // Trigger an order:created event
            // The event will be handled by this very same class

            $createdEvent = new Event\OrderCreated($order);

            $this->handleStateChange($createdEvent);
            $this->eventBus->handle($createdEvent);

        } elseif ($event instanceof Event\CheckoutFailed) {

            $payment = $event->getPayment();
            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

            $payment->setLastError($event->getReason());
            $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);

            // Call OrderProcessor to create a new payment
            $this->orderProcessor->process($event->getOrder());
        }
    }

    private function handleStateChange(Event $event)
    {
        if (isset($this->eventNameToTransition[$event::messageName()])) {

            $order = $event->getOrder();

            $transition = $this->eventNameToTransition[$event::messageName()];

            $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

            $stateMachine->apply($transition, true);
        }
    }
}
