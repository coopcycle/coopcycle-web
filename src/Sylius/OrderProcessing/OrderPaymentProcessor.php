<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\LoggingUtils;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
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
        CurrencyContextInterface $currencyContext,
        private PaymentContext $paymentContext,
        private EdenredClient $edenredClient,
        private StripeManager $stripeManager,
        private GatewayResolver $gatewayResolver,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
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
                $this->checkoutLogger->info(sprintf('OrderPaymentProcessor | payment #%d | removed', $payment->getId()),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
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

        $payments = $order->getPayments()->filter(function (PaymentInterface $payment) use ($targetState): bool {
            return $payment->getState() === $targetState;
        });

        /** @var Collection */
        $paymentsToKeep = new ArrayCollection();

        switch ($this->paymentContext->getMethod()) {
            case 'EDENRED':

                $edenredAmount = $this->edenredClient->getMaxAmount($order);

                if ($edenredAmount < $order->getTotal()) {
                    // FIXME
                    // Do not hardcode this here
                    $card = $this->paymentMethodRepository->findOneByCode('CARD');
                    $cardPayment = $this->upsertPayment($order, $payments, $card, ($order->getTotal() - $edenredAmount), $targetState);
                    $paymentsToKeep->add($cardPayment);
                }

                // FIXME
                // Do not hardcode this here
                $edenred = $this->paymentMethodRepository->findOneByCode('EDENRED');
                $edenredPayment = $this->upsertPayment($order, $payments, $edenred, $edenredAmount, $targetState);
                $paymentsToKeep->add($edenredPayment);

                break;

            case 'CASH_ON_DELIVERY':
                // FIXME
                // Do not hardcode this here
                $cash = $this->paymentMethodRepository->findOneByCode('CASH_ON_DELIVERY');
                $cashPayment = $this->upsertPayment($order, $payments, $cash, $order->getTotal(), $targetState);
                $paymentsToKeep->add($cashPayment);

                break;

            case 'CARD':
            default:
                // FIXME
                // Do not hardcode this here
                $card = $this->paymentMethodRepository->findOneByCode('CARD');
                $cardPayment = $this->upsertPayment($order, $payments, $card, $order->getTotal(), $targetState);
                if ('stripe' === $this->gatewayResolver->resolve()) {
                    // Make sure to call StripeManager::configurePayment()
                    // It will resolve the Stripe account that will be used
                    $this->stripeManager->configurePayment($cardPayment);
                }
                $paymentsToKeep->add($cardPayment);
        }

        foreach ($payments as $payment) {
            if (!$paymentsToKeep->contains($payment)) {
                $order->removePayment($payment);
            }
        }
    }

    private function upsertPayment(BaseOrderInterface $order,
        Collection $payments, PaymentMethodInterface $method, int $amount, string $targetState = PaymentInterface::STATE_CART): PaymentInterface
    {
        /** @var PaymentInterface|false */
        $payment = $payments->filter(fn (PaymentInterface $payment): bool => $payment->getMethod() === $method)->first();

        if ($payment) {
            $payment->setCurrencyCode($this->currencyContext->getCurrencyCode());
            $payment->setAmount($amount);

            $this->checkoutLogger->info(sprintf('OrderPaymentProcessor | finished | (updated) payment #%d: %d ',
                $payment->getId(),
                $payment->getAmount()), ['order' => $this->loggingUtils->getOrderId($order)]);

            return $payment;
        }

        $payment = $this->paymentFactory->createWithAmountAndCurrencyCode(
            $amount,
            $this->currencyContext->getCurrencyCode()
        );
        $payment->setMethod($method);
        $payment->setState($targetState);

        $order->addPayment($payment);

        $this->checkoutLogger->info(sprintf('OrderPaymentProcessor | finished | (new) payment: %d', $payment->getAmount()),
            ['order' => $this->loggingUtils->getOrderId($order)]);

        return $payment;
    }
}
