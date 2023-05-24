<?php

namespace AppBundle\Service;

use AppBundle\Entity\User;
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
        $restaurantPaysStripeFee = $restaurant->getContract()->isRestaurantPaysStripeFee();

        if (null !== $stripeAccount) {

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

        if (null === $stripeAccount || !$restaurantPaysStripeFee) {
            /** For payments done directly in the platform account
             *  we send the Customer paramater to associate the payment to the customer.
             * (this param is mandatory when the payment method belongs to the customer, i.e. when user selects a saved pm)
             */
            if ($order->getCustomer() && $order->getCustomer()->hasUser()) {
                $stripeCustomer = $order->getCustomer()->getUser()->getStripeCustomerId();

                if (null !== $stripeCustomer) {
                    $attrs['customer'] = $stripeCustomer;
                }
            }
        }

        return $payload + $attrs;
    }

    /**
     * @return Stripe\PaymentIntent
     */
    public function createIntent(PaymentInterface $payment, $savePaymentMethod = false): Stripe\PaymentIntent
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

        $payload = $this->handleSaveOfPaymentMethod($payment, $payload, $stripeOptions, $savePaymentMethod);

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

    public function createTransfersForHub(PaymentInterface $payment, Stripe\StripeObject $charge)
    {
        $order = $payment->getOrder();

        if (!$order->hasVendor() || !$order->isMultiVendor()) {
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

    /**
     * @return Stripe\Customer
     */
    public function createCustomer(User $user)
    {
        if (null !== $user->getStripeCustomerId()) {
            return Stripe\Customer::retrieve($user->getStripeCustomerId());
        }

        // create customer on Platform Account
        $customer =  Stripe\Customer::create([
            'email' => $user->getEmail(),
            'name' => $user->getUserName()
        ]);

        $user->setStripeCustomerId($customer->id);

        return $customer;
    }

    /**
     * @return Stripe\SetupIntent
     */
    public function createSetupIntent(PaymentInterface $payment, $paymentMethod)
    {
        $user = $payment->getOrder()->getCustomer()->getUser();
        $customerId = $user->getStripeCustomerId();

        if (null === $customerId) {
            $customer = $this->createCustomer($user);
            $customerId = $customer->id;
        }

        return Stripe\SetupIntent::create([
            'payment_method' => $paymentMethod,
            'payment_method_types' => ['card'],
            'usage' => 'on_session',
            'customer' => $customerId,
            'confirm' => true
        ]);
    }

    public function attachPaymentMethodToCustomer(PaymentInterface $payment)
    {
        $user = $payment->getOrder()->getCustomer()->getUser();
        $customerId = $user->getStripeCustomerId();

        if (null === $customerId) {
            $customer = $this->createCustomer($user);
            $customerId = $customer->id;
        }

        $paymentMethod = Stripe\PaymentMethod::retrieve($payment->getPaymentMethodToSave());

        if (null !== $paymentMethod) {
            $paymentMethod->attach([
                'customer' => $customerId
            ]);
        }
    }

    /**
     * @see https://stripe.com/docs/connect/cloning-customers-across-accounts
     * @see https://stripe.com/docs/payments/payment-methods/connect#cloning-payment-methods
     *
     * We clone the PaymentMethod in the connected account and then we use the clonned payment method id
     * when we create the PaymentIntent to create the direct charge in the connected account.
     *
     * @return Stripe\PaymentMethod
     */
    public function clonePaymentMethodToConnectedAccount(PaymentInterface $payment)
    {
        $payload = [
            'payment_method' => $payment->getPaymentMethod()
        ];

        if ($payment->getOrder()->getCustomer()->hasUser()) {
            $user = $payment->getOrder()->getCustomer()->getUser();
            $customerId = $user->getStripeCustomerId();

            if (null === $customerId) {
                $customer = $this->createCustomer($user);
                $customerId = $customer->id;
            }

            $payload['customer'] = $customerId;
        }

        $stripeOptions = $this->getStripeOptions($payment);

        return Stripe\PaymentMethod::create($payload, $stripeOptions);
    }

    public function getCustomerPaymentMethods($customerId)
    {
        return Stripe\Customer::allPaymentMethods($customerId, ['type' => 'card']);
    }

    /**
     * @see https://stripe.com/docs/api/payment_methods/attach
     */
    private function handleSaveOfPaymentMethod(PaymentInterface $payment, $payload, $stripeOptions, $savePaymentMethod)
    {
        $notSavingForConnectedAccount = !isset($stripeOptions['stripe_account']) || null == $stripeOptions['stripe_account'];

        if ($savePaymentMethod && $notSavingForConnectedAccount) {
            // when there is not a connected account save payment method directly on platform account using 'setup_future_usage' param
            // https://stripe.com/docs/api/payment_intents/create#create_payment_intent-setup_future_usage

            $user = $payment->getOrder()->getCustomer()->getUser();

            if (null == $user->getStripeCustomerId()) {
                $customer = $this->createCustomer($payment->getOrder()->getCustomer()->getUser());
                $payload['customer'] = $customer->id;
            } else {
                $payload['customer'] = $user->getStripeCustomerId();
            }

            $payload['setup_future_usage'] = 'on_session';
        }

        return $payload;
    }

}
