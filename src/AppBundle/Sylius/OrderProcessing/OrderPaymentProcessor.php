<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\StripePayment;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    private $doctrine;
    private $currencyContext;

    public function __construct(
        ManagerRegistry $doctrine,
        CurrencyContextInterface $currencyContext)
    {
        $this->doctrine = $doctrine;
        $this->currencyContext = $currencyContext;
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

        $targetStates = [
            OrderInterface::STATE_CART => PaymentInterface::STATE_CART,
            OrderInterface::STATE_NEW  => PaymentInterface::STATE_NEW
        ];

        if (!in_array($order->getState(), array_keys($targetStates))) {
            return;
        }

        $targetState = $targetStates[$order->getState()];

        $lastPayment = $order->getLastPayment($targetState);

        if (null !== $lastPayment) {
            $lastPayment->setCurrencyCode($this->currencyContext->getCurrencyCode());
            $lastPayment->setAmount($order->getTotal());

            return;
        }

        $payment = new StripePayment();
        $payment->setCurrencyCode($this->currencyContext->getCurrencyCode());
        $payment->setAmount($order->getTotal());
        $payment->setState($targetState);

        $order->addPayment($payment);
    }
}
