<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Payment\Gateway;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Webmozart\Assert\Assert;

class CapturePayment
{
    private $gateway;
    private $logger;

    public function __construct(Gateway $gateway, LoggerInterface $logger)
    {
        $this->gateway = $gateway;
        $this->logger = $logger;
    }

    public function __invoke(Event $event)
    {
        Assert::isInstanceOfAny($event, [
            Event\OrderFulfilled::class,
            Event\OrderCancelled::class,
        ]);

        if ($event instanceof Event\OrderCancelled) {
            if ($event->getReason() !== OrderInterface::CANCEL_REASON_NO_SHOW) {
                return;
            }
        }

        $order = $event->getOrder();

        $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

        // This happens when a B2B customer has placed an order
        if (null === $payment && !$order->hasVendor()) {
            return;
        }

        $isFreeOrder = null === $payment && $order->isFree();
        $isCashOnDelivery = null !== $payment && $payment->isCashOnDelivery();

        if ($isFreeOrder || $isCashOnDelivery) {
            return;
        }

        $completedPayment =
            $order->getLastPayment(PaymentInterface::STATE_COMPLETED);

        if (null !== $completedPayment && $completedPayment->isGiropay()) {
            return;
        }

        // TODO Handle error if payment is NULL

        try {

            $this->gateway->capture($payment);

        } catch (\Exception $e) {
            // FIXME
            // If we land here, there is a severe problem
            // Maybe schedule a retry?
            $this->logger->error($e->getMessage());
        }
    }
}
