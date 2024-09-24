<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\User;
use AppBundle\Entity\Vendor;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use SM\StateMachine\StateMachineInterface;
use Stripe;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\AppBundle\StripeTrait;

class StripeManagerTest extends TestCase
{
    use ProphecyTrait;
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $stripeManager;

    public function setUp(): void
    {
        $this->setUpStripe();

        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->settingsManager
            ->isStripeLivemode()
            ->willReturn(false);

        $this->settingsManager
            ->get('stripe_secret_key')
            ->willReturn(self::$stripeApiKey);


        $this->stripeManager = new StripeManager(
            $this->settingsManager->reveal(),
            new NullLogger()
        );
    }

    private function createRestaurant($stripeUserId = null, $paysStripeFee = true)
    {
        $restaurant = $this->prophesize(Restaurant::class);

        if ($stripeUserId) {
            $stripeAccount = $this->prophesize(StripeAccount::class);
            $stripeAccount
                ->getStripeUserId()
                ->willReturn($stripeUserId);
            $restaurant
                ->getStripeAccount(false)
                ->willReturn($stripeAccount->reveal());
        } else {
            $restaurant
                ->getStripeAccount(false)
                ->willReturn(null);
        }

        $contract = $this->prophesize(Contract::class);
        $contract
            ->isRestaurantPaysStripeFee()
            ->willReturn($paysStripeFee);

        $restaurant
            ->getContract()
            ->willReturn($contract->reveal());

        return $restaurant->reveal();
    }

    public function testCaptureWithPaymentIntent()
    {
        // FIXME
        // The Payment Intent returned by Stripe Mock
        // has capture_method = "automatic" & "amount_capturable" = 0
        // so we can't test the capture

        $this->markTestSkipped();

        $payment = new Payment();
        $payment->setStripeUserId('acct_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => '',
            'capture_method' => 'manual',
            'amount_capturable' => 3000,
        ]);
        $payment->setPaymentIntent($paymentIntent);

        $restaurant = $this->createRestaurant('acct_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('GET', '/v1/payment_intents/pi_12345678', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents/pi_12345678/capture', 'acct_123456', ["amount_to_capture" => 3000]);

        $this->stripeManager->capture($payment);
    }

    public function testCreateTransfersForHub()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant1 = $this->createRestaurant('acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('acct_456', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2 ]);

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getTotal()
            ->willReturn(3000);
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(true);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([ $restaurant1, $restaurant2 ]));
        $order
            ->getVendor()
            ->willReturn($hub->reveal());
        $order
            ->getTransferAmount(Argument::type(LocalBusiness::class))
            ->will(function ($args) use ($restaurant1, $restaurant2) {
                if ($args[0] === $restaurant1) {
                    return 1130;
                }
                if ($args[0] === $restaurant2) {
                    return 370;
                }
            });

        // Total = 30.00
        // Items = 22.50
        // Fees  =  7.50

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/transfers', [
            'amount' => 1130, // = 1700 - (750 * 0.76),
            'currency' => 'eur',
            'destination' => 'acct_123',
            'source_transaction' => 'ch_123456',
        ]);
        $this->shouldSendStripeRequest('POST', '/v1/transfers', [
            'amount' => 370, // = 550 - (750 * 0.24),
            'currency' => 'eur',
            'destination' => 'acct_456',
            'source_transaction' => 'ch_123456',
        ]);

        $charge = Stripe\Charge::constructFrom([
            'id' => 'ch_123456',
        ]);

        $this->stripeManager->createTransfersForHub($payment, $charge);
    }

    public function testCreateTransfersForHubWithOneRestaurant()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant1 = $this->createRestaurant('acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('acct_456', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2 ]);

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getTotal()
            ->willReturn(3000);
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(true);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([ $restaurant1 ]));
        $order
            ->getVendor()
            ->willReturn($hub->reveal());
        $order
            ->getTransferAmount(Argument::type(LocalBusiness::class))
            ->will(function ($args) use ($restaurant1, $restaurant2) {
                if ($args[0] === $restaurant1) {
                    return 1130;
                }
            });

        // Total = 30.00
        // Items = 22.50
        // Fees  =  7.50

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/transfers', [
            'amount' => 1130, // = 1700 - (750 * 0.76),
            'currency' => 'eur',
            'destination' => 'acct_123',
            'source_transaction' => 'ch_123456',
        ]);

        $charge = Stripe\Charge::constructFrom([
            'id' => 'ch_123456',
        ]);

        $this->stripeManager->createTransfersForHub($payment, $charge);
    }

    public function testCreateIntent()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethod('pm_123456');

        $restaurant = $this->createRestaurant('acct_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->getFeeTotal()
            ->willReturn(750);

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents', 'acct_123456', [
            "amount" => 3000,
            "currency" => "eur",
            "description" => "Order ABC",
            "payment_method" => "pm_123456",
            "confirmation_method" => "manual",
            "confirm" => "true",
            "capture_method" => "manual",
            "application_fee_amount" => 750
        ]);

        $this->stripeManager->createIntent($payment);
    }

    public function testCreateIntentForNotConnectedAccount()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethod('pm_123456');

        $restaurant = $this->createRestaurant();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->getFeeTotal()
            ->willReturn(750);

        $user = $this->prophesize(User::class);

        $user
            ->getStripeCustomerId()
            ->willReturn('cus_123456abcdef');

        $customer = $this->prophesize(Customer::class);

        $customer
            ->hasUser()
            ->willReturn(true);

        $customer
            ->getUser()
            ->willReturn($user->reveal());

        $customer
            ->hasUser()
            ->willReturn(true);

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/payment_intents', [
            "amount" => 3000,
            "currency" => "eur",
            "description" => "Order ABC",
            "payment_method" => "pm_123456",
            "confirmation_method" => "manual",
            "confirm" => "true",
            "capture_method" => "manual",
            "customer" => "cus_123456abcdef"
        ]);

        $this->stripeManager->createIntent($payment);
    }

    public function testCreateIntentWithSavePaymentForFutureUsage()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethod('pm_123456');

        $restaurant = $this->createRestaurant();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->getFeeTotal()
            ->willReturn(750);

        $user = $this->prophesize(User::class);

        $user
            ->getStripeCustomerId()
            ->willReturn('cus_123456abcdef');

        $customer = $this->prophesize(Customer::class);

        $customer
            ->hasUser()
            ->willReturn(true);

        $customer
            ->getUser()
            ->willReturn($user->reveal());

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/payment_intents', [
            "amount" => 3000,
            "currency" => "eur",
            "description" => "Order ABC",
            "payment_method" => "pm_123456",
            "confirmation_method" => "manual",
            "confirm" => "true",
            "capture_method" => "manual",
            "setup_future_usage" => "on_session",
            "customer" => "cus_123456abcdef"
        ]);

        $this->stripeManager->createIntent($payment, true);
    }

    public function testCreateSetupIntentForSavePaymentMethod()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');

        $user = $this->prophesize(User::class);

        $user
            ->getStripeCustomerId()
            ->willReturn('cus_123456abcdef');

        $customer = $this->prophesize(Customer::class);

        $customer
            ->getUser()
            ->willReturn($user->reveal());

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/setup_intents', [
            "payment_method_types" => ["card"],
            "payment_method" => "pm_123456",
            "usage" => "on_session",
            "customer" => "cus_123456abcdef",
            "confirm" => true
        ]);

        $this->stripeManager->createSetupIntent($payment, "pm_123456");
    }

    public function testClonePaymentMethodForConnectedAccount()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethod('pm_123456');

        $restaurant = $this->createRestaurant('acct_123456');

        $user = $this->prophesize(User::class);

        $user
            ->getStripeCustomerId()
            ->willReturn('cus_123456abcdef');

        $customer = $this->prophesize(Customer::class);

        $customer
            ->getUser()
            ->willReturn($user->reveal());

        $customer
            ->hasUser()
            ->willReturn(true);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->getFeeTotal()
            ->willReturn(750);

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_methods', 'acct_123456', [
            "payment_method" => "pm_123456",
            "customer" => "cus_123456abcdef"
        ]);

        $this->stripeManager->clonePaymentMethodToConnectedAccount($payment);
    }

    public function testCreateIntentWithTransferData()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethod('pm_123456');

        $restaurant = $this->createRestaurant('acct_123456', $paysStripeFee = false);

        $customer = $this->prophesize(Customer::class);

        $customer
            ->hasUser()
            ->willReturn(false);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->getTotal()
            ->willReturn(3000);
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/payment_intents', [
            "amount" => 3000,
            "currency" => "eur",
            "description" => "Order ABC",
            "payment_method" => "pm_123456",
            "confirmation_method" => "manual",
            "confirm" => "true",
            "capture_method" => "manual",
            "transfer_data" => [
                "destination" => "acct_123456",
                "amount" => 2250
            ]
        ]);

        $this->stripeManager->createIntent($payment);
    }

    public function testConfirmIntent()
    {
        $restaurant = $this->createRestaurant('acct_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->isMultiVendor()
            ->willReturn(false);

        $payment = new Payment();
        $payment->setStripeUserId('acct_123456');
        $payment->setOrder($order->reveal());

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $payment->setPaymentIntent($paymentIntent);

        $this->shouldSendStripeRequestForAccount('GET',  '/v1/payment_intents/pi_12345678', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents/pi_12345678/confirm', 'acct_123456');

        $this->stripeManager->confirmIntent($payment);
    }

    public function testCreateIntentWithAmountBreakdownForEdenred()
    {
        $edenred = $this->prophesize(PaymentMethodInterface::class);
        $edenred->getCode()->willReturn('EDENRED');

        $card = $this->prophesize(PaymentMethodInterface::class);
        $card->getCode()->willReturn('CARD');

        $edenredPayment = new Payment();
        $edenredPayment->setAmount(2650);
        $edenredPayment->setCurrencyCode('EUR');
        $edenredPayment->setPaymentMethod('pm_123456');
        $edenredPayment->setMethod($edenred->reveal());

        $payment = new Payment();
        $payment->setAmount(350);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethod('pm_123456');
        $payment->setMethod($card->reveal());

        $restaurant = $this->createRestaurant('acct_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn($restaurant);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([ $edenredPayment, $payment ]));

        $user = $this->prophesize(User::class);

        $user
            ->getStripeCustomerId()
            ->willReturn('cus_123456abcdef');

        $customer = $this->prophesize(Customer::class);

        $customer
            ->hasUser()
            ->willReturn(true);
        $customer
            ->getUser()
            ->willReturn($user->reveal());

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents', 'acct_123456', [
            "amount" => 350,
            "currency" => "eur",
            "description" => "Order ABC",
            "payment_method" => "pm_123456",
            "confirmation_method" => "manual",
            "confirm" => "true",
            "capture_method" => "manual",
            "customer" => "cus_123456abcdef",
        ]);

        $this->stripeManager->createIntent($payment);
    }

    public function testAttachPaymentMethodToCustomer()
    {
        $order = $this->prophesize(OrderInterface::class);

        $user = $this->prophesize(User::class);

        $user
            ->getStripeCustomerId()
            ->willReturn('cus_123456abcdef');

        $customer = $this->prophesize(Customer::class);

        $customer
            ->getUser()
            ->willReturn($user->reveal());

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $payment = new Payment();
        $payment->setOrder($order->reveal());

        $payment->setPaymentDataToSaveAndReuse('pm_12345678');

        $this->shouldSendStripeRequest('GET',  '/v1/payment_methods/pm_12345678');
        $this->shouldSendStripeRequest('POST', '/v1/payment_methods/pm_12345678/attach', [
            'customer' => 'cus_123456abcdef'
        ]);

        $this->stripeManager->attachPaymentMethodToCustomer($payment);
    }
}
