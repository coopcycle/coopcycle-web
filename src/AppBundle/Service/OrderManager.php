<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Task;
use AppBundle\Event\OrderCancelEvent;
use AppBundle\Event\OrderCreateEvent;
use AppBundle\Event\OrderAcceptEvent;
use AppBundle\Event\PaymentAuthorizeEvent;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\Common\Persistence\ManagerRegistry;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Stripe;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderManager
{
    private $doctrine;
    private $routing;
    private $stateMachineFactory;
    private $settingsManager;
    private $eventDispatcher;

    public function __construct(
        ManagerRegistry $doctrine,
        RoutingInterface $routing,
        StateMachineFactoryInterface $stateMachineFactory,
        SettingsManager $settingsManager,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->doctrine = $doctrine;
        $this->routing = $routing;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->settingsManager = $settingsManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function pay(OrderInterface $order, $stripeToken)
    {
        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $stripePayment->setStripeToken($stripeToken);

        $this->authorizePayment($order);
    }

    public function create(OrderInterface $order)
    {
        $orderStateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $orderStateMachine->apply(OrderTransitions::TRANSITION_CREATE);

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $paymentStateMachine = $this->stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);
        $paymentStateMachine->apply(PaymentTransitions::TRANSITION_CREATE);
    }

    public function accept(OrderInterface $order)
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $stateMachine->apply(OrderTransitions::TRANSITION_ACCEPT);
    }

    public function refuse(OrderInterface $order)
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $stateMachine->apply(OrderTransitions::TRANSITION_REFUSE);
    }

    public function ready(OrderInterface $order)
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $stateMachine->apply(OrderTransitions::TRANSITION_READY);
    }

    public function fulfill(OrderInterface $order)
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $stateMachine->apply(OrderTransitions::TRANSITION_FULFILL);
    }

    public function cancel(OrderInterface $order)
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $stateMachine->apply(OrderTransitions::TRANSITION_CANCEL);
    }

    public function createDelivery(OrderInterface $order)
    {
        if (null !== $order->getDelivery()) {
            return;
        }

        $pickupAddress = $order->getRestaurant()->getAddress();
        $dropoffAddress = $order->getShippingAddress();

        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $dropoffDoneBefore = $order->getShippedAt();

        $pickupDoneBefore = clone $dropoffDoneBefore;
        $pickupDoneBefore->modify(sprintf('-%d seconds', $duration));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress($pickupAddress);
        $pickup->setDoneBefore($pickupDoneBefore);

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setDoneBefore($dropoffDoneBefore);

        $delivery = new Delivery();
        $delivery->addTask($pickup);
        $delivery->addTask($dropoff);

        $order->setDelivery($delivery);

        $this->doctrine->getManagerForClass(Delivery::class)->persist($delivery);
        $this->doctrine->getManagerForClass(Delivery::class)->flush();
    }

    public function authorizePayment(OrderInterface $order)
    {
        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_secret_key'));

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $stripeToken = $stripePayment->getStripeToken();

        if (null === $stripeToken) {
            return;
        }

        try {

            $stateMachine = $this->stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

            $charge = Stripe\Charge::create(array(
                'amount' => $order->getTotal(),
                'currency' => strtolower($stripePayment->getCurrencyCode()),
                'source' => $stripeToken,
                'description' => sprintf('Order %s', $order->getNumber()),
                // To authorize a payment without capturing it,
                // make a charge request that also includes the capture parameter with a value of false.
                // This instructs Stripe to only authorize the amount on the customer’s card.
                'capture' => false,
            ));

            $stripePayment->setCharge($charge->id);

            // TODO Use constant
            $stateMachine->apply('authorize');

        } catch (\Exception $e) {
            $stripePayment->setLastError($e->getMessage());
            $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);
        } finally {
            $stateMachine->apply(PaymentTransitions::TRANSITION_AUTHORIZE);
            $this->doctrine->getManagerForClass(StripePayment::class)->flush();
        }
    }

    public function capturePayment(OrderInterface $order)
    {
        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

        if (null === $stripePayment) {
            return;
        }

        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_secret_key'));

        $stateMachine = $this->stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

        try {

            $charge = Stripe\Charge::retrieve($stripePayment->getCharge());
            if ($charge->captured) {
                throw new \Exception('Charge already captured');
            }

            $charge->capture();

            $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);

        } catch (\Exception $e) {
            $stripePayment->setLastError($e->getMessage());
            $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);
        }
    }

    public function completePayment(PaymentInterface $payment)
    {
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
    }

    public function dispatchOrderEvent(OrderInterface $order, $eventName)
    {
        switch ($eventName) {
            case OrderCancelEvent::NAME:
                $this->eventDispatcher->dispatch(OrderCancelEvent::NAME, new OrderCancelEvent($order));
                break;
            case OrderCreateEvent::NAME:
                $this->eventDispatcher->dispatch(OrderCreateEvent::NAME, new OrderCreateEvent($order));
                break;
            case OrderAcceptEvent::NAME:
                $this->eventDispatcher->dispatch(OrderAcceptEvent::NAME, new OrderAcceptEvent($order));
                break;
        }
    }

    public function dispatchPaymentEvent(PaymentInterface $payment, $eventName)
    {
        switch ($eventName) {
            case PaymentAuthorizeEvent::NAME:
                $this->eventDispatcher->dispatch(PaymentAuthorizeEvent::NAME, new PaymentAuthorizeEvent($payment));
                break;
        }
    }
}
