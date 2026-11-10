<?php

namespace AppBundle\Payment;

use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class GatewayResolver
{
    /**
     * Payment methods that can only be processed by Paygreen.
     *
     * Meal vouchers, and the wallets we only support through Paygreen.
     */
    private const PAYGREEN_ONLY_METHODS = [
        'CONECS',
        'SWILE',
        'RESTOFLASH',
        'APPLE_PAY',
        'GOOGLE_PAY',
    ];

    private $country;
    private $mercadopagoCountries;
    private $forceStripe;

    public function __construct(string $country,
        $mercadopagoCountries = [],
        $forceStripe = false,
        private bool $paygreenEnabled = false,
        private ?StripeAvailability $stripeAvailability = null)
    {
        $this->country = $country;
        $this->mercadopagoCountries = $mercadopagoCountries;
        $this->forceStripe = $forceStripe;
    }

    public function resolveForCountry($country)
    {
        if ($this->forceStripe) {
            return 'stripe';
        }

        if (in_array($country, $this->mercadopagoCountries)) {
            return 'mercadopago';
        }

        if ('gh' === $country) {
            return 'pawapay';
        }

        return 'stripe';
    }

    /**
     * Resolves the gateway used to process *card* payments for this order.
     *
     * Shops may have both Stripe & Paygreen configured. In that case, card payments
     * are processed by Stripe, which has a better success rate, while meal vouchers
     * keep being processed by Paygreen.
     *
     * @see OrderInterface::supportsPaygreen() for meal vouchers support.
     */
    public function resolveForOrder(OrderInterface $order)
    {
        if ($order->supportsPaygreen() && !$this->canUseStripeForOrder($order)) {
            return 'paygreen';
        }

        return $this->resolveForCountry($this->country);
    }

    /**
     * Resolves the gateway for an order being paid with a given payment method.
     *
     * Some methods can only be processed by Paygreen, whatever the gateway
     * used for card payments.
     */
    public function resolveForOrderAndMethod(OrderInterface $order, ?string $method)
    {
        if (null !== $method && in_array($method, self::PAYGREEN_ONLY_METHODS)) {
            return 'paygreen';
        }

        return $this->resolveForOrder($order);
    }

    public function resolve()
    {
        return $this->resolveForCountry($this->country);
    }

    public function supports($gateway): bool
    {
        if ($gateway === 'paygreen') {
            return $this->paygreenEnabled;
        }

        return $gateway === $this->resolveForCountry($this->country);
    }

    public function resolveForPayment(PaymentInterface $payment)
    {
        $payment->getDetails();

        if ($payment->hasPaygreenPaymentOrderId()) {

            // Hotfix Stripe being used instead of PayGreen
            // In this case the Payment has both "paygreen_payment_order_id" & "payment_intent"
            // https://github.com/coopcycle/coopcycle-app/issues/2079
            if ($payment->hasPaymentIntent()) {
                return 'stripe';
            }

            return 'paygreen';
        }

        // Meal vouchers are always processed by Paygreen,
        // even when card payments are processed by Stripe
        $method = $payment->getMethod();
        if (null !== $method && in_array($method->getCode(), self::PAYGREEN_ONLY_METHODS)) {
            return 'paygreen';
        }

        return $this->resolveForOrder($payment->getOrder());
    }

    /**
     * Whether Stripe can be used to charge a card for this order.
     */
    private function canUseStripeForOrder(OrderInterface $order): bool
    {
        if (null === $this->stripeAvailability) {
            return false;
        }

        // Stripe is not the gateway used in this country
        if ('stripe' !== $this->resolveForCountry($this->country)) {
            return false;
        }

        return $this->stripeAvailability->supportsRestaurant($order->getRestaurant());
    }
}
