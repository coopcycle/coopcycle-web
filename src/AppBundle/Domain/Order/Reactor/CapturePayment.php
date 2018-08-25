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

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

        if (null === $stripePayment) {
            // TODO Handle error
            return;
        }

        try {

            $this->stripeManager->capture($stripePayment);
            $this->eventBus->handle(new OrderFulfilled($order, $stripePayment));

        } catch (\Exception $e) {
            // FIXME
            // If we land here, there is a severe problem
            // Maybe schedule a retry?
        }
    }
}
