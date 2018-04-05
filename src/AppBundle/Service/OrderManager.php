<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Task;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\Common\Persistence\ManagerRegistry;
use Predis\Client as Redis;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Stripe;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class OrderManager
{
    private $doctrine;
    private $redis;
    private $serializer;
    private $routing;
    private $stateMachineFactory;
    private $eventDispatcher;

    public function __construct(
        ManagerRegistry $doctrine,
        Redis $redis,
        SerializerInterface $serializer,
        RoutingInterface $routing,
        StateMachineFactoryInterface $stateMachineFactory,
        SettingsManager $settingsManager,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->serializer = $serializer;
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
        $delivery->setOrder($order);
        $delivery->addTask($pickup);
        $delivery->addTask($dropoff);

        $this->doctrine->getManagerForClass(Delivery::class)->persist($delivery);
        $this->doctrine->getManagerForClass(Delivery::class)->flush();
    }

    public function authorizePayment(OrderInterface $order)
    {
        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_secret_key'));

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $stateMachine = $this->stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

        try {

            $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE);

            $stripeToken = $stripePayment->getStripeToken();

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

    public function publishRedisEvent(OrderInterface $order, $eventName)
    {
        switch ($eventName) {
            case 'order.accept':
                $channel = sprintf('order:%d:state_changed', $order->getId());
                $this->redis->publish($channel, $this->serializer->serialize($order, 'json', ['groups' => ['order']]));
                break;

            case 'order.payment_authorized':
                if (null !== $order->getRestaurant()) {
                    $channel = sprintf('restaurant:%d:orders', $order->getRestaurant()->getId());
                    $this->redis->publish($channel, $this->serializer->serialize($order, 'jsonld', ['groups' => ['order']]));
                }
                break;
        }
    }
}
