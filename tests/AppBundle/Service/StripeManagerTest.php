<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Vendor;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
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
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);

        $this->stripeManager = new StripeManager(
            $this->settingsManager->reveal(),
            $this->urlGenerator->reveal(),
            'secret',
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

    public function testAuthorizeCreatesDirectChargeWithNoConnectAccount()
    {
        $payment = new Payment();
        $payment->setAmount(900);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant = $this->createRestaurant();

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($restaurant)
            );

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/charges', [
            'amount' => 900,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
        ]);

        $this->stripeManager->authorize($payment);
    }

    public function testAuthorizeCreatesDirectChargeWithConnectAccount()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant = $this->createRestaurant('acct_123');

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($restaurant)
            );

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges', 'acct_123', [
            'amount' => 3000,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
            'application_fee' => 750
        ]);

        $this->stripeManager->authorize($payment);
    }

    public function testAuthorizeCreatesDirectChargeWithOwnAccount()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant = $this->createRestaurant('acct_123', $paysStripeFee = false);

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
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($restaurant)
            );

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/charges', [
            'amount' => 3000,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
            'destination' => [
                'account' => 'acct_123',
                'amount' => (3000 - 750)
            ]
        ]);

        $this->stripeManager->authorize($payment);
    }

    public function testAuthorizeAddsApplicationFee()
    {
        $payment = new Payment();
        $payment->setAmount(900);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');

        $restaurant = $this->createRestaurant('acct_123');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($restaurant)
            );
        $order
            ->getLastPayment(PaymentInterface::STATE_NEW)
            ->willReturn($payment);
        $order
            ->getTotal()
            ->willReturn(900);
        $order
            ->getFeeTotal()
            ->willReturn(250);
        $order
            ->getNumber()
            ->willReturn('000001');

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges', 'acct_123', [
            'amount' => 900,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
            'application_fee' => 250
        ]);

        $this->stripeManager->authorize($payment);
    }

    public function testAuthorizeWithHub()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant1 = $this->createRestaurant('acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('acct_456', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);

        $vendor = new Vendor();
        $vendor->setHub($hub->reveal());

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
            ->getVendor()
            ->willReturn($vendor);

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/charges', [
            'amount' => 3000,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
        ]);

        $this->stripeManager->authorize($payment);
    }

    public function testCaptureWithOwnAccount()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');

        $restaurant = $this->createRestaurant('acct_123456', $paysStripeFee = false);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant));

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('GET', '/v1/charges/ch_123456');
        $this->shouldSendStripeRequest('POST', '/v1/charges/ch_123456/capture');

        $this->stripeManager->capture($payment);
    }

    public function testCaptureWithConnectAccount()
    {
        $payment = new Payment();
        $payment->setStripeUserId('acct_123456');
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');

        $restaurant = $this->createRestaurant('acct_123456', $paysStripeFee = true);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant));

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('GET', '/v1/charges/ch_123456', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges/ch_123456/capture', 'acct_123456');

        $this->stripeManager->capture($payment);
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
            ->willReturn(Vendor::withRestaurant($restaurant));
        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('GET', '/v1/payment_intents/pi_12345678', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents/pi_12345678/capture', 'acct_123456', ["amount_to_capture" => 3000]);

        $this->stripeManager->capture($payment);
    }

    public function testCaptureWithHubs()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant1 = $this->createRestaurant('acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('acct_456', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2 ]);

        $vendor = new Vendor();
        $vendor->setHub($hub->reveal());

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
            ->getVendors()
            ->willReturn([ $restaurant1, $restaurant2 ]);
        $order
            ->getVendor()
            ->willReturn($vendor);
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

        $this->shouldSendStripeRequest('GET', '/v1/charges/ch_123456');
        $this->shouldSendStripeRequest('POST', '/v1/charges/ch_123456/capture');
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

        $this->stripeManager->capture($payment);
    }

    public function testCaptureWithHubsAndOneRestaurant()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant1 = $this->createRestaurant('acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('acct_456', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2 ]);

        $vendor = new Vendor();
        $vendor->setHub($hub->reveal());

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
            ->getVendors()
            ->willReturn([ $restaurant1 ]);
        $order
            ->getVendor()
            ->willReturn($vendor);
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

        $this->shouldSendStripeRequest('GET', '/v1/charges/ch_123456');
        $this->shouldSendStripeRequest('POST', '/v1/charges/ch_123456/capture');
        $this->shouldSendStripeRequest('POST', '/v1/transfers', [
            'amount' => 1130, // = 1700 - (750 * 0.76),
            'currency' => 'eur',
            'destination' => 'acct_123',
            'source_transaction' => 'ch_123456',
        ]);

        $this->stripeManager->capture($payment);
    }

    public function testCreateIntent()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');
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
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant));
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

    public function testCreateIntentWithTransferData()
    {
        $payment = new Payment();
        $payment->setStripeToken('tok_123456');
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');
        $payment->setPaymentMethod('pm_123456');

        $restaurant = $this->createRestaurant('acct_123456', $paysStripeFee = false);

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
            ->willReturn(Vendor::withRestaurant($restaurant));
        $order
            ->getTotal()
            ->willReturn(3000);
        $order
            ->getFeeTotal()
            ->willReturn(750);

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
            ->willReturn(Vendor::withRestaurant($restaurant));

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

    public function testAuthorizeWithSourceCreatesDirectChargeWithConnectAccount()
    {
        $source = Stripe\Source::constructFrom([
            'id' => 'src_12345678',
            'type' => 'giropay',
            'client_secret' => '',
            'redirect' => [
                'url' => 'http://example.com'
            ]
        ]);

        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setCurrencyCode('EUR');
        $payment->setSource($source);

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);
        $contract = $this->prophesize(Contract::class);

        $restaurant = $this->createRestaurant('acct_123');

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($restaurant)
            );

        $payment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('GET',  '/v1/sources/src_12345678', 'acct_123');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges', 'acct_123', [
            'amount' => 3000,
            'currency' => 'eur',
            'source' => 'src_12345678',
            'description' => 'Order 000001',
            'capture' => 'true',
            'application_fee' => 750
        ]);

        $this->stripeManager->authorize($payment);
    }
}
