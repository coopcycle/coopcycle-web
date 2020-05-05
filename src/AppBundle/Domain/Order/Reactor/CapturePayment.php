<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Service\StripeManager;
use Sylius\Component\Payment\Model\PaymentInterface;

class CapturePayment
{
    private $stripeManager;

    public function __construct(StripeManager $stripeManager)
    {
        $this->stripeManager = $stripeManager;
    }

    public function __invoke(OrderFulfilled $event)
    {
        $order = $event->getOrder();

        $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

        // This happens when a B2B customer has placed an order
        if (null === $payment && null === $order->getRestaurant()) {
            return;
        }

        $isFreeOrder = null === $payment && !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;

        if ($isFreeOrder) {
            return;
        }

        $completedPayment =
            $order->getLastPayment(PaymentInterface::STATE_COMPLETED);

        if (null !== $completedPayment && $completedPayment->hasSource()
            && 'giropay' === $completedPayment->getSourceType()) {
            return;
        }

        // TODO Handle error if payment is NULL

        try {

            $this->stripeManager->capture($payment);

        } catch (\Exception $e) {
            // FIXME
            // If we land here, there is a severe problem
            // Maybe schedule a retry?
        }
    }
}
