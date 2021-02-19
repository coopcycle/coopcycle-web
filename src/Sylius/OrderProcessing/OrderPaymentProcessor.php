<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Webmozart\Assert\Assert;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    private $paymentMethodRepository;
    private $paymentFactory;
    private $currencyContext;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentFactoryInterface $paymentFactory,
        CurrencyContextInterface $currencyContext)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentFactory = $paymentFactory;
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
            foreach ($order->getPayments() as $payment) {
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

        // FIXME
        // Do not hardcode this here
        $card = $this->paymentMethodRepository->findOneByCode('CARD');

        $payment = $this->paymentFactory->createWithAmountAndCurrencyCode(
            $order->getTotal(),
            $this->currencyContext->getCurrencyCode()
        );
        $payment->setMethod($card);
        $payment->setState($targetState);

        $order->addPayment($payment);
    }
}
