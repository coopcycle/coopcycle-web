<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use AppBundle\Utils\OrderTimeHelper;
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Model\PaymentInterface;

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
            Event\OrderPreparationStarted::messageName() => OrderTransitions::TRANSITION_START_PREPARING,
            Event\OrderPreparationFinished::messageName() => OrderTransitions::TRANSITION_FINISH_PREPARING,
            Event\OrderRestored::messageName()  => OrderTransitions::TRANSITION_RESTORE,
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

        if ($event instanceof Event\OrderFulfilled && $event->shouldCompletePayment()) {
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

        if ($event instanceof Event\OrderRestored) {

            foreach ($order->getPayments() as $payment) {
                // FIXME
                // This will only work for payment methods supporting the authorize/capture
                $payment->setState(PaymentInterface::STATE_AUTHORIZED);
            }

            $delivery = $order->getDelivery();
            if (null !== $delivery) {
                foreach ($delivery->getTasks() as $task) {
                    // TODO
                    // Maybe we could use TaskManager::restore()
                    if ($task->hasEvent(TaskDone::messageName())) {
                        $task->setStatus(Task::STATUS_DONE);
                    } else {
                        $task->setStatus(Task::STATUS_TODO);
                    }
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

            if (null !== $payment) {

                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

                if ($stateMachine->can(PaymentTransitions::TRANSITION_AUTHORIZE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_AUTHORIZE);
                } elseif ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                }
            }

            // Trigger an order:created event
            // The event will be handled by this very same class

            $createdEvent = new Event\OrderCreated($order);

            // set generateEvent to false, because we don't want to send order:state_changed event
            // before/together with order:created event
            $this->handleStateChange($createdEvent, false);
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

    private function handleStateChange(Event $event, $generateEvent = true)
    {
        if (isset($this->eventNameToTransition[$event::messageName()])) {

            $order = $event->getOrder();

            $transition = $this->eventNameToTransition[$event::messageName()];

            $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

            if ($stateMachine->apply($transition, true) && $generateEvent) {
                $orderStateChangeEvent = new Event\OrderStateChanged($order, $event);
                $this->eventBus->handle($orderStateChangeEvent);
            }
        }
    }
}
