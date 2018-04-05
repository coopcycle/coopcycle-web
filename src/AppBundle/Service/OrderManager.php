<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Task;
use AppBundle\Event\OrderAcceptEvent;
use AppBundle\Event\OrderCancelEvent;
use AppBundle\Service\RoutingInterface;
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
    private $notificationManager;
    private $eventDispatcher;

    public function __construct(
        ManagerRegistry $doctrine,
        Redis $redis,
        SerializerInterface $serializer,
        RoutingInterface $routing,
        StateMachineFactoryInterface $stateMachineFactory,
        SettingsManager $settingsManager,
        NotificationManager $notificationManager,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->serializer = $serializer;
        $this->routing = $routing;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->settingsManager = $settingsManager;
        $this->notificationManager = $notificationManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function pay(Order $order, $stripeToken)
    {
        $this->payment->authorize($order, $stripeToken);

        $order->setStatus(Order::STATUS_WAITING);

        $channel = sprintf('restaurant:%d:orders', $order->getRestaurant()->getId());
        $this->redis->publish($channel, $this->serializer->serialize($order, 'jsonld'));
    }

    public function accept(Order $order)
    {
        // Order MUST have status = WAITING
        if ($order->getStatus() !== Order::STATUS_WAITING) {
            throw new \Exception(sprintf('Order #%d cannot be accepted anymore', $order->getId()));
        }

        $this->payment->capture($order);

        $order->setStatus(Order::STATUS_ACCEPTED);

        $this->eventDispatcher->dispatch(OrderAcceptEvent::NAME, new OrderAcceptEvent($order));
    }

    public function cancel(Order $order)
    {
        $order->setStatus(Order::STATUS_CANCELED);
        $order->getDelivery()->setStatus(Delivery::STATUS_CANCELED);

        $this->eventDispatcher->dispatch(OrderCancelEvent::NAME, new OrderCancelEvent($order));
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
        $delivery->setSyliusOrder($order);
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

    public function sendAcceptEmail(OrderInterface $order)
    {
        // TODO
    }

    public function sendRefuseEmail(OrderInterface $order)
    {
        // TODO
    }

    public function sendConfirmEmail(OrderInterface $order)
    {
        $this->notificationManager->notifyDeliveryConfirmed($order, $order->getCustomer()->getEmail());
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
