<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

trait GiropayTrait
{
    protected StripeManager $stripeManager;

    protected function refundCompletedGiropayPayments(OrderInterface $order)
    {
        $completedGiropayPayments = $order->getPayments()->filter(function (PaymentInterface $payment): bool {
            return $payment->isGiropay()
                && $payment->getState() === PaymentInterface::STATE_COMPLETED;
        });

        if (count($completedGiropayPayments) === 0) {
            return;
        }

        foreach ($completedGiropayPayments as $payment) {
            $this->stripeManager->refund($payment);
        }
    }
}
