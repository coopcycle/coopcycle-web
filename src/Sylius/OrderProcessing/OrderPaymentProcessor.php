<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\LoggingUtils;
use AppBundle\Service\PaygreenManager;
use AppBundle\Service\PawapayManager;
use AppBundle\Service\GiftCard\GiftCardManager;
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
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentFactoryInterface $paymentFactory,
        private CurrencyContextInterface $currencyContext,
        private PaymentContext $paymentContext,
        private EdenredClient $edenredClient,
        private StripeManager $stripeManager,
        private PaygreenManager $paygreenManager,
        private PawapayManager $pawapayManager,
        private GatewayResolver $gatewayResolver,
        private GiftCardManager $giftCardManager,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {
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

        if ($order->isFoodtech()) {
            $targetStates = [
                OrderInterface::STATE_CART => PaymentInterface::STATE_CART,
                OrderInterface::STATE_NEW  => PaymentInterface::STATE_NEW
            ];
        } else {
            // non-foodtech/Package Delivery orders
            $targetStates = [
                OrderInterface::STATE_CART => PaymentInterface::STATE_CART,
                OrderInterface::STATE_NEW  => PaymentInterface::STATE_NEW,
                // Allow to have an "accepted" order with incomplete payment
                OrderInterface::STATE_ACCEPTED  => PaymentInterface::STATE_NEW
            ];
        }

        if (!in_array($order->getState(), array_keys($targetStates))) {
            return;
        }

        $targetState = $targetStates[$order->getState()];

        $payments = $order->getPayments()->filter(function (PaymentInterface $payment) use ($targetState): bool {
            return $payment->getState() === $targetState;
        });

        /** @var Collection $paymentsToKeep */
        $paymentsToKeep = new ArrayCollection();

        // The gift card leg is allocated before anything else, and outside
        // the switch below, because it is not one of the choices in it.
        //
        // PaymentContext holds a single, mutually-exclusive method code --
        // the customer pays by card, or by meal voucher, or in cash. A gift
        // card is none of those: it is an amount already paid for, applied
        // to the cart like a coupon, and whatever it doesn't cover is still
        // paid by whichever method the customer picked. So it takes its
        // share first, and the switch divides up what's left.
        $giftCardAmount = $this->giftCardManager->getMaxAmount($order, $this->paymentContext->getMethod());

        if ($giftCardAmount > 0) {
            $giftCardMethod = $this->paymentMethodRepository->findOneByCode('GIFT_CARD');
            $giftCardPayment = $this->upsertPayment($order, $payments, $giftCardMethod, $giftCardAmount, $targetState);
            $paymentsToKeep->add($giftCardPayment);
        }

        $remainder = $order->getTotal() - $giftCardAmount;

        // Nothing left to pay. No second leg is created -- a zero-amount
        // payment would be rejected by every gateway it reached, and there
        // is genuinely nothing for one to do.
        if ($remainder > 0) {

            switch ($this->paymentContext->getMethod()) {
                case 'CONECS':
                case 'SWILE':
                case 'RESTOFLASH':

                    // TODO
                    // Maybe here we should create 2 payments,
                    // using the logic to populate the "eligible_amounts" field of a payment order
                    $method = $this->paymentMethodRepository->findOneByCode($this->paymentContext->getMethod());
                    $payment = $this->upsertPayment($order, $payments, $method, $remainder, $targetState);

                    $this->paygreenManager->createPaymentOrder($payment);

                    $paymentsToKeep->add($payment);

                    break;
                case 'EDENRED':

                    // Capped at the remainder: the eligible amount is worked
                    // out from the whole order, which is more than is left to
                    // pay once a gift card has taken its share.
                    $edenredAmount = min($this->edenredClient->getMaxAmount($order), $remainder);

                    if ($edenredAmount < $remainder) {
                        // FIXME
                        // Do not hardcode this here
                        $card = $this->paymentMethodRepository->findOneByCode('CARD');
                        $cardPayment = $this->upsertPayment($order, $payments, $card, ($remainder - $edenredAmount), $targetState);
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
                    $cashPayment = $this->upsertPayment($order, $payments, $cash, $remainder, $targetState);
                    $paymentsToKeep->add($cashPayment);

                    break;

                case 'CARD':
                default:
                    // FIXME
                    // Do not hardcode this here
                    $card = $this->paymentMethodRepository->findOneByCode('CARD');
                    $cardPayment = $this->upsertPayment($order, $payments, $card, $remainder, $targetState);
                    // Methods such as Apple Pay & Google Pay fall into this branch,
                    // and can only be processed by Paygreen
                    switch ($this->gatewayResolver->resolveForOrderAndMethod($order, $this->paymentContext->getMethod())) {
                        case 'stripe':
                            // Make sure to call StripeManager::configurePayment()
                            // It will resolve the Stripe account that will be used
                            $this->stripeManager->configurePayment($cardPayment);
                            break;
                        case 'paygreen':
                            $this->paygreenManager->createPaymentOrder($cardPayment);
                            break;
                        // FIXME
                        // Actually for pawaPay it should not be "CARD"
                        // but something else like "MOBILE_PAYMENT"
                        case 'pawapay':
                            $this->pawapayManager->createPaymentPage($cardPayment);
                            break;
                    }
                    $paymentsToKeep->add($cardPayment);
            }

        }

        // Anything no longer part of the plan goes: this is what removes the
        // card leg when a gift card grows to cover the whole order.
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

            $this->checkoutLogger->debug(sprintf('OrderPaymentProcessor | finished | (updated) payment #%d: %d ',
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

        $this->checkoutLogger->debug(sprintf('OrderPaymentProcessor | finished | (new) payment: %d', $payment->getAmount()),
            ['order' => $this->loggingUtils->getOrderId($order)]);

        return $payment;
    }
}
