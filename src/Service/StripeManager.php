<?php

namespace AppBundle\Service;

use Hashids\Hashids;
use Psr\Log\LoggerInterface;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeManager
{
    private $settingsManager;
    private $urlGenerator;
    private $secret;
    private $logger;

    const STRIPE_API_VERSION = '2019-09-09';

    public function __construct(
        SettingsManager $settingsManager,
        UrlGeneratorInterface $urlGenerator,
        string $secret,
        LoggerInterface $logger)
    {
        $this->settingsManager = $settingsManager;
        $this->urlGenerator = $urlGenerator;
        $this->secret = $secret;
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

        $restaurant = $order->getRestaurant();
        if (null === $restaurant) {
            return;
        }

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

        $restaurant = $order->getRestaurant();
        if (null === $restaurant) {
            return $options;
        }

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
            'amount' => $payment->getAmount(),
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

        $intent = Stripe\PaymentIntent::create($payload, $stripeOptions);

        return $intent;
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

        if ($source = $payment->getSource()) {

            return $source;
        }

        throw new \Exception(sprintf('No Stripe source found in payment #%d', $payment->getId()));
    }

    public function shouldCapture(PaymentInterface $payment): bool
    {
        if ($sourceId = $payment->getSource()) {

            $source =
                Stripe\Source::retrieve($sourceId, $this->getStripeOptions($payment));

            // @see https://stripe.com/docs/api/sources/object#source_object-type

            return in_array($source->type, [
                'giropay',
                // We need to add this for unit tests
                // because stripe-mock always returns this type
                'ach_credit_transfer',
            ]);
        }

        return false;
    }

    /**
     * @return Stripe\Charge
     */
    public function authorize(PaymentInterface $payment)
    {
        $this->configure();

        $order = $payment->getOrder();

        $stripeParams = [
            'amount' => $payment->getAmount(),
            'currency' => strtolower($payment->getCurrencyCode()),
            'source' => $this->resolveSource($payment),
            'description' => sprintf('Order %s', $order->getNumber()),
            // @see https://stripe.com/docs/api/charges/create#create_charge-capture
            // Whether to immediately capture the charge. Defaults to true.
            // When false, the charge issues an authorization (or pre-authorization),
            // and will need to be captured later.
            // Uncaptured charges expire in seven days.
            'capture' => $this->shouldCapture($payment),
        ];

        $stripeOptions = [];

        if (null !== $order->getRestaurant()) {

            $livemode = $this->settingsManager->isStripeLivemode();
            $stripeAccount = $order->getRestaurant()->getStripeAccount($livemode);

            if (!is_null($stripeAccount)) {

                $restaurantPaysStripeFee = $order->getRestaurant()->getContract()->isRestaurantPaysStripeFee();
                $applicationFee = $order->getFeeTotal();

                if ($restaurantPaysStripeFee) {
                    // needed only when using direct charges (the charge is linked to the restaurant's Stripe account)
                    $payment->setStripeUserId($stripeAccount->getStripeUserId());
                    $stripeOptions['stripe_account'] = $stripeAccount->getStripeUserId();
                    $stripeParams['application_fee'] = $applicationFee;
                } else {
                    $stripeParams['destination'] = array(
                        'account' => $stripeAccount->getStripeUserId(),
                        'amount' => $order->getTotal() - $applicationFee
                    );
                }
            }
        }

        return Stripe\Charge::create(
            $stripeParams,
            $stripeOptions
        );
    }

    /**
     * @return Stripe\Charge|Stripe\PaymentIntent
     */
    public function capture(PaymentInterface $payment)
    {
        $this->configure();

        if (null !== $payment->getPaymentIntent()) {
            // TODO Exception
            $intent = Stripe\PaymentIntent::retrieve(
                $payment->getPaymentIntent(),
                $this->getStripeOptions($payment)
            );

            $intent->capture([
                'amount_to_capture' => $payment->getAmount()
            ]);

            // TODO Return charge
            return $intent;
        }

        $charge = Stripe\Charge::retrieve(
            $payment->getCharge(),
            $this->getStripeOptions($payment)
        );

        // When we are using sources (like giropay),
        // the charge is already captured
        if ($charge->captured) {

            return $charge;
        }

        $charge->capture();

        return $charge;
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
            'charge' => $payment->getCharge(),
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
     * @return Stripe\Source
     */
    public function createGiropaySource(PaymentInterface $payment, string $ownerName)
    {
        $this->configure();

        $hashids = new Hashids($this->secret, 8);

        $returnUrl = $this->urlGenerator->generate('payment_confirm', [
            'hashId' => $hashids->encode($payment->getId()),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $stripeOptions = $this->getStripeOptions($payment);

        return Stripe\Source::create([
            'type' => 'giropay',
            'amount' => $payment->getAmount(),
            'currency' => strtolower($payment->getCurrencyCode()),
            'owner' => [
                'name' => $ownerName
            ],
            'redirect' => [
                'return_url' => $returnUrl
            ]
        ], $stripeOptions);
    }
}
