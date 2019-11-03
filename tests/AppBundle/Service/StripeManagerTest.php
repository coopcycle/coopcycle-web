<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Contract;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use SM\StateMachine\StateMachineInterface;
use Stripe;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\AppBundle\StripeTrait;

class StripeManagerTest extends TestCase
{
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

    public function testAuthorizeCreatesDirectChargeWithNoConnectAccount()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setAmount(900);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

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

        $stripePayment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/charges', [
            'amount' => 900,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
        ]);

        $this->stripeManager->authorize($stripePayment);
    }

    public function testAuthorizeCreatesDirectChargeWithConnectAccount()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setAmount(3000);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

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

        $stripePayment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges', 'acct_123', [
            'amount' => 3000,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
            'application_fee' => 750
        ]);

        $this->stripeManager->authorize($stripePayment);
    }

    public function testAuthorizeCreatesDirectChargeWithOwnAccount()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setAmount(3000);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

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

        $stripePayment->setOrder($order->reveal());

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

        $this->stripeManager->authorize($stripePayment);
    }

    public function testAuthorizeAddsApplicationFee()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setAmount(900);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

        $restaurant = $this->createRestaurant('acct_123');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getLastPayment(PaymentInterface::STATE_NEW)
            ->willReturn($stripePayment);
        $order
            ->getTotal()
            ->willReturn(900);
        $order
            ->getFeeTotal()
            ->willReturn(250);
        $order
            ->getNumber()
            ->willReturn('000001');

        $stripePayment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges', 'acct_123', [
            'amount' => 900,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
            'application_fee' => 250
        ]);

        $this->stripeManager->authorize($stripePayment);
    }

    public function testCaptureWithOwnAccount()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');
        $stripePayment->setCharge('ch_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->createRestaurant('acct_123456', $paysStripeFee = false));

        $stripePayment->setOrder($order->reveal());

        $this->shouldSendStripeRequest('GET', '/v1/charges/ch_123456');
        $this->shouldSendStripeRequest('POST', '/v1/charges/ch_123456/capture');

        $this->stripeManager->capture($stripePayment);
    }

    public function testCaptureWithConnectAccount()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setStripeUserId('acct_123456');
        $stripePayment->setCurrencyCode('EUR');
        $stripePayment->setCharge('ch_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->createRestaurant('acct_123456', $paysStripeFee = true));

        $stripePayment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('GET', '/v1/charges/ch_123456', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/charges/ch_123456/capture', 'acct_123456');

        $this->stripeManager->capture($stripePayment);
    }

    public function testCaptureWithPaymentIntent()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setStripeUserId('acct_123456');
        $stripePayment->setAmount(3000);
        $stripePayment->setCurrencyCode('EUR');
        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $stripePayment->setPaymentIntent($paymentIntent);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->createRestaurant('acct_123456'));
        $stripePayment->setOrder($order->reveal());

        $this->shouldSendStripeRequestForAccount('GET', '/v1/payment_intents/pi_12345678', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents/pi_12345678/capture', 'acct_123456', ["amount_to_capture" => 3000]);

        $this->stripeManager->capture($stripePayment);
    }

    public function testCreateIntent()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setAmount(3000);
        $stripePayment->setCurrencyCode('EUR');
        $stripePayment->setCharge('ch_123456');
        $stripePayment->setPaymentMethod('pm_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->getRestaurant()
            ->willReturn($this->createRestaurant('acct_123456'));
        $order
            ->getFeeTotal()
            ->willReturn(750);

        $stripePayment->setOrder($order->reveal());

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

        $this->stripeManager->createIntent($stripePayment);
    }

    public function testCreateIntentWithTransferData()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setAmount(3000);
        $stripePayment->setCurrencyCode('EUR');
        $stripePayment->setCharge('ch_123456');
        $stripePayment->setPaymentMethod('pm_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getNumber()
            ->willReturn('ABC');
        $order
            ->getRestaurant()
            ->willReturn($this->createRestaurant('acct_123456', $paysStripeFee = false));
        $order
            ->getTotal()
            ->willReturn(3000);
        $order
            ->getFeeTotal()
            ->willReturn(750);

        $stripePayment->setOrder($order->reveal());

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

        $this->stripeManager->createIntent($stripePayment);
    }

    public function testConfirmIntent()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getRestaurant()
            ->willReturn($this->createRestaurant('acct_123456'));

        $stripePayment = new StripePayment();
        $stripePayment->setStripeUserId('acct_123456');
        $stripePayment->setOrder($order->reveal());

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $stripePayment->setPaymentIntent($paymentIntent);

        $this->shouldSendStripeRequestForAccount('GET',  '/v1/payment_intents/pi_12345678', 'acct_123456');
        $this->shouldSendStripeRequestForAccount('POST', '/v1/payment_intents/pi_12345678/confirm', 'acct_123456');

        $this->stripeManager->confirmIntent($stripePayment);
    }
}
