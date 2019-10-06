<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderDropped;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Service\StripeManager;
use SimpleBus\Message\Bus\MessageBus;
use Sylius\Component\Payment\Model\PaymentInterface;

class CapturePayment
{
    private $stripeManager;
    private $eventBus;

    public function __construct(
        StripeManager $stripeManager,
        MessageBus $eventBus)
    {
        $this->stripeManager = $stripeManager;
        $this->eventBus = $eventBus;
    }

    public function __invoke(OrderDropped $event)
    {
        $order = $event->getOrder();

        $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

        // This happens when a B2B customer has placed an order
        if (null === $payment && null === $order->getRestaurant()) {
            $this->eventBus->handle(new OrderFulfilled($order));
            return;
        }

        $isFreeOrder = null === $payment && !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;

        if ($isFreeOrder) {
            $this->eventBus->handle(new OrderFulfilled($order));
            return;
        }

        // TODO Handle error if payment is NULL

        try {

            $this->stripeManager->capture($payment);
            $this->eventBus->handle(new OrderFulfilled($order, $payment));

        } catch (\Exception $e) {
            // FIXME
            // If we land here, there is a severe problem
            // Maybe schedule a retry?
        }
    }
}
