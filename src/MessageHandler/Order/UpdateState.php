<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Order\Event\OrderPreparationFinished;
use AppBundle\Domain\Order\Event\OrderPreparationStarted;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Domain\Order\Event\OrderRestored;
use AppBundle\Domain\Order\Event\OrderStateChanged;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use AppBundle\Utils\OrderTimeHelper;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * This Reactor is responsible for updating the state of the aggregate.
 */
#[AsMessageHandler(priority: -10)]
 class UpdateState
{
    private $eventNameToTransition = [];

    public function __construct(
        private readonly StateMachineFactoryInterface $stateMachineFactory,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly MessageBusInterface $eventBus,
        private readonly OrderTimeHelper $orderTimeHelper)
    {

        $this->eventNameToTransition = [
            OrderCreated::messageName()   => OrderTransitions::TRANSITION_CREATE,
            OrderAccepted::messageName()  => OrderTransitions::TRANSITION_ACCEPT,
            OrderRefused::messageName()   => OrderTransitions::TRANSITION_REFUSE,
            OrderCancelled::messageName() => OrderTransitions::TRANSITION_CANCEL,
            OrderFulfilled::messageName() => OrderTransitions::TRANSITION_FULFILL,
            OrderPreparationStarted::messageName() => OrderTransitions::TRANSITION_START_PREPARING,
            OrderPreparationFinished::messageName() => OrderTransitions::TRANSITION_FINISH_PREPARING,
            OrderRestored::messageName()  => OrderTransitions::TRANSITION_RESTORE,
        ];
    }

    public function __invoke(OrderCreated|OrderAccepted|OrderRefused|OrderCancelled|OrderFulfilled|OrderPreparationStarted|OrderPreparationFinished|OrderRestored|CheckoutSucceeded|CheckoutFailed $event)
    {
        if ($event instanceof CheckoutSucceeded || $event instanceof CheckoutFailed) {
            $this->handleCheckoutEvent($event);
            return;
        }

        $this->handleStateChange($event);

        $order = $event->getOrder();

        if ($event instanceof OrderCreated) {
            foreach ($order->getPayments() as $payment) {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                if ($stateMachine->can(PaymentTransitions::TRANSITION_CREATE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE);
                }
            }
        }

        if ($event instanceof OrderFulfilled && $event->shouldCompletePayment()) {
            foreach ($order->getPayments() as $payment) {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                    $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                }
            }
        }

        if ($event instanceof OrderCancelled) {

            $transition = $event->getReason() === OrderInterface::CANCEL_REASON_NO_SHOW ?
                PaymentTransitions::TRANSITION_COMPLETE : PaymentTransitions::TRANSITION_CANCEL;

            foreach ($order->getPayments() as $payment) {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                if ($stateMachine->can($transition)) {
                    $stateMachine->apply($transition);
                }
            }
        }

        if ($event instanceof OrderRestored) {

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

    private function handleCheckoutEvent(CheckoutSucceeded|CheckoutFailed $event)
    {
        if ($event instanceof CheckoutSucceeded) {

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

                if ($payment instanceof PaymentInterface) {
                    $payment = [ $payment ];
                }

                foreach ($payment as $p) {
                    $stateMachine = $this->stateMachineFactory->get($p, PaymentTransitions::GRAPH);

                    if ($stateMachine->can(PaymentTransitions::TRANSITION_AUTHORIZE)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_AUTHORIZE);
                    } elseif ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                    }
                }
            }

            // Trigger an order:created event
            // The event will be handled by this very same class

            $createdEvent = new OrderCreated($order);

            // set generateEvent to false, because we don't want to send order:state_changed event
            // before/together with order:created event
            $this->handleStateChange($createdEvent, false);
            $this->eventBus->dispatch($createdEvent);

        } elseif ($event instanceof CheckoutFailed) {

            $payment = $event->getPayment();
            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

            $payment->setLastError($event->getReason());
            $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);

            // Call OrderProcessor to create a new payment
            $this->orderProcessor->process($event->getOrder());
        }
    }

    private function handleStateChange(OrderCreated|OrderAccepted|OrderRefused|OrderCancelled|OrderFulfilled|OrderPreparationStarted|OrderPreparationFinished|OrderRestored $event, $generateEvent = true)
    {
        if (isset($this->eventNameToTransition[$event::messageName()])) {

            $order = $event->getOrder();

            $transition = $this->eventNameToTransition[$event::messageName()];

            $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

            if ($stateMachine->apply($transition, true) && $generateEvent) {
                $orderStateChangeEvent = new OrderStateChanged($order, $event);
                $this->eventBus->dispatch($orderStateChangeEvent);
            }
        }
    }
}
