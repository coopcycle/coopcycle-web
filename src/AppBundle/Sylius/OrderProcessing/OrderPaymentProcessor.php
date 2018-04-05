<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\StripePayment;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (OrderInterface::STATE_CANCELLED === $order->getState()) {
            return;
        }

        if (0 === $order->getTotal()) {
            foreach ($order->getPayments(PaymentInterface::STATE_CART) as $payment) {
                $order->removePayment($payment);
            }

            return;
        }

        if (!$order->hasPayments()) {
            $payment = new StripePayment();
            $payment->setCurrencyCode('EUR');
            $payment->setAmount($order->getTotal());

            $order->addPayment($payment);
        }
    }
}
