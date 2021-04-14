<?php

namespace AppBundle\Service;

use Hashids\Hashids;
use Psr\Log\LoggerInterface;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;

class StripeManager
{
    private $settingsManager;
    private $logger;

    const STRIPE_API_VERSION = '2019-09-09';

    public function __construct(
        SettingsManager $settingsManager,
        LoggerInterface $logger)
    {
        $this->settingsManager = $settingsManager;
        $this->logger = $logger;
    }

    public function configure()
    {
        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_secret_key'));
        Stripe\Stripe::setApiVersion(self::STRIPE_API_VERSION);
    }

    public function configurePayment(PaymentInterface $payment)
    {
        $order = $payment->getOrder();

        if (!$order->hasVendor() || $order->isMultiVendor()) {
            return;
        }

        $restaurant = $order->getRestaurant();

        $livemode = $this->settingsManager->isStripeLivemode();
        $stripeAccount = $restaurant->getStripeAccount($livemode);

        if (null !== $stripeAccount && $restaurant->getContract()->isRestaurantPaysStripeFee()) {
            $payment->setStripeUserId($stripeAccount->getStripeUserId());
        }
    }

    private function getStripeOptions(PaymentInterface $payment)
    {
        $options = [];

        $order = $payment->getOrder();

        if (!$order->hasVendor() || $order->isMultiVendor()) {
            return $options;
        }

        $restaurant = $order->getRestaurant();

        $livemode = $this->settingsManager->isStripeLivemode();
        $stripeAccount = $restaurant->getStripeAccount($livemode);

        if (null !== $stripeAccount && $restaurant->getContract()->isRestaurantPaysStripeFee()) {
            $options['stripe_account'] = $stripeAccount->getStripeUserId();
        }

        return $options;
    }

    private function configureCreateIntentPayload(PaymentInterface $payment, array $payload)
    {
        $order = $payment->getOrder();

        $restaurant = $order->getRestaurant();
        if (null === $restaurant) {

            return $payload;
        }

        // If it is a complementary payment,
        // we do not take application fee
        if ($payment->isEdenredWithCard()) {

            return $payload;
        }

        $attrs = [];

        $livemode = $this->settingsManager->isStripeLivemode();
        $stripeAccount = $restaurant->getStripeAccount($livemode);

        if (null !== $stripeAccount) {

            $restaurantPaysStripeFee = $restaurant->getContract()->isRestaurantPaysStripeFee();
            $applicationFee = $order->getFeeTotal();

            // @see https://stripe.com/docs/payments/payment-intents/use-cases#connected-accounts
            if ($restaurantPaysStripeFee) {
                $attrs['application_fee_amount'] = $applicationFee;
            } else {
                $attrs['transfer_data'] = array(
                    'destination' => $stripeAccount->getStripeUserId(),
                    'amount' => $order->getTotal() - $applicationFee
                );
            }
        }

        return $payload + $attrs;
    }

    /**
     * @return Stripe\PaymentIntent
     */
    public function createIntent(PaymentInterface $payment): Stripe\PaymentIntent
    {
        $this->configure();

        $order = $payment->getOrder();

        $payload = [
            'amount' => $payment->getAmountForMethod('CARD'),
            'currency' => strtolower($payment->getCurrencyCode()),
            'description' => sprintf('Order %s', $order->getNumber()),
            'payment_method' => $payment->getPaymentMethod(),
            'confirmation_method' => 'manual',
            'confirm' => true,
            // @see https://stripe.com/docs/payments/payment-intents/use-cases#separate-auth-capture
            // @see https://stripe.com/docs/payments/payment-intents/creating-payment-intents#separate-authorization-and-capture
            'capture_method' => 'manual'
            // 'statement_descriptor' => '...',
        ];

        $this->configurePayment($payment);

        $payload = $this->configureCreateIntentPayload($payment, $payload);
        $stripeOptions = $this->getStripeOptions($payment);

        $this->logger->info(
            sprintf('Order #%d | StripeManager::createIntent | %s', $order->getId(), json_encode($payload))
        );

        return Stripe\PaymentIntent::create($payload, $stripeOptions);
    }

    /**
     * @see https://stripe.com/docs/payments/giropay/accept-a-payment#create-payment-intent
     *
     * @param PaymentInterface $payment
     * @return Stripe\PaymentIntent
     */
    public function createGiropayIntent(PaymentInterface $payment): Stripe\PaymentIntent
    {
        $this->configure();

        $order = $payment->getOrder();

        $payload = [
            'amount' => $payment->getAmount(),
            'currency' => strtolower($payment->getCurrencyCode()),
            'description' => sprintf('Order %s', $order->getNumber()),
            'payment_method_types' => ['giropay'],
            // TODO Add statement descriptor
            // 'statement_descriptor' => '...',
        ];

        $this->configurePayment($payment);

        $payload = $this->configureCreateIntentPayload($payment, $payload);
        $stripeOptions = $this->getStripeOptions($payment);

        $this->logger->info(
            sprintf('Order #%d | StripeManager::createGiropayIntent | %s', $order->getId(), json_encode($payload))
        );

        return Stripe\PaymentIntent::create($payload, $stripeOptions);
    }

    /**
     * @return Stripe\PaymentIntent
     */
    public function confirmIntent(PaymentInterface $payment): Stripe\PaymentIntent
    {
        $this->configure();

        $stripeOptions = $this->getStripeOptions($payment);

        $intent = Stripe\PaymentIntent::retrieve(
            $payment->getPaymentIntent(),
            $stripeOptions
        );

        $this->logger->info(
            sprintf('Order #%d | StripeManager::confirmIntent | %s', $payment->getOrder()->getId(), $intent->id)
        );

        $intent->confirm();

        return $intent;
    }

    private function resolveSource(PaymentInterface $payment)
    {
        if ($stripeToken = $payment->getStripeToken()) {

            return $stripeToken;
        }

        throw new \Exception(sprintf('No Stripe source found in payment #%d', $payment->getId()));
    }

    /**
     * @return Stripe\PaymentIntent
     */
    public function capture(PaymentInterface $payment)
    {
        $this->configure();

        // TODO Exception
        $intent = Stripe\PaymentIntent::retrieve(
            $payment->getPaymentIntent(),
            $this->getStripeOptions($payment)
        );

        // Make sure the payment intent needs to be captured
        // When using Giropay, it's not needed
        if ($intent->capture_method === 'manual' && $intent->amount_capturable > 0) {
            $intent->capture([
                'amount_to_capture' => $payment->getAmountForMethod('CARD')
            ]);
        }

        if ($charge = $this->getChargeFromPaymentIntent($intent)) {
            $this->createTransfersForHub($payment, $charge);
        }

        // TODO Return charge
        return $intent;
    }

    /**
     * @return Stripe\Refund
     */
    public function refund(PaymentInterface $payment, $amount = null)
    {
        // FIXME
        // Check if the charge was made in test or live mode
        // To achieve this, we need to store a "livemode" key in payment details

        $this->configure();

        $stripeAccount = $payment->getStripeUserId();
        $stripeOptions = array();

        if (null !== $stripeAccount) {
            $stripeOptions['stripe_account'] = $stripeAccount;
        }

        $args = [
            'payment_intent' => $payment->getPaymentIntent()
        ];

        if (null !== $amount) {
            $amount = (int) $amount;
            if ($amount !== $payment->getAmount()) {
                $args['amount'] = $amount;
            }
        }

        return Stripe\Refund::create($args, $stripeOptions);
    }

    /**
     * @return Stripe\StripeObject|null
     */
    private function getChargeFromPaymentIntent(Stripe\PaymentIntent $intent): ?Stripe\StripeObject
    {
        // @see https://stripe.com/docs/api/payment_intents/object#payment_intent_object-charges
        if (count($intent->charges->data) === 1) {
            return current($intent->charges->data);
        }

        return null;
    }

    public function createTransfersForHub(PaymentInterface $payment, Stripe\StripeObject $charge)
    {
        $order = $payment->getOrder();

        if (!$order->hasVendor()) {
            return;
        }

        $restaurants = $order->getRestaurants();

        if (count($restaurants) > 0) {

            $livemode = $this->settingsManager->isStripeLivemode();

            foreach ($restaurants as $restaurant) {

                $stripeAccount  = $restaurant->getStripeAccount($livemode);

                // This may happen in dev environment
                if (null === $stripeAccount) {
                    continue;
                }

                $transferAmount = $order->getTransferAmount($restaurant);

                if ($transferAmount > 0) {
                    // @see https://stripe.com/docs/connect/charges-transfers
                    Stripe\Transfer::create([
                        'amount' => $transferAmount,
                        'currency' => strtolower($payment->getCurrencyCode()),
                        'destination' => $stripeAccount->getStripeUserId(),
                        // @see https://stripe.com/docs/connect/charges-transfers#transfer-availability
                        'source_transaction' => $charge->id,
                    ]);
                }
            }
        }
    }
}
