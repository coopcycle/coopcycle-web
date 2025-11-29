<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Payment\Gateway;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler()]
class CapturePayment
{
    private $gateway;
    private $logger;

    public function __construct(Gateway $gateway, LoggerInterface $logger)
    {
        $this->gateway = $gateway;
        $this->logger = $logger;
    }

    public function __invoke(OrderFulfilled|OrderCancelled $event)
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

        $authorizedOnlinePayments = $order->getPayments()->filter(function (PaymentInterface $payment): bool {
            return $payment->getState() === PaymentInterface::STATE_AUTHORIZED
                && !$payment->isCashOnDelivery();
        });

        if (count($authorizedOnlinePayments) === 0) {
            return;
        }

        foreach ($authorizedOnlinePayments as $payment) {
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
}
