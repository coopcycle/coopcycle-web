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
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use SM\StateMachine\StateMachineInterface;
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
            $this->settingsManager->reveal()
        );
    }

    public function testAuthorizeCreatesDirectCharge()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setAmount(900);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);
        $contract = $this->prophesize(Contract::class);

        $contract
            ->isRestaurantPaysStripeFee()
            ->willReturn(true);

        $restaurant
            ->getContract()
            ->willReturn($contract->reveal());
        $restaurant
            ->getStripeAccount(false)
            ->willReturn(null);

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

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

    public function testAuthorizeAddsApplicationFee()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setAmount(900);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);
        $contract = $this->prophesize(Contract::class);

        $stripeAccount
            ->getStripeUserId()
            ->willReturn('acct_123');

        $contract
            ->isRestaurantPaysStripeFee()
            ->willReturn(true);

        $restaurant
            ->getContract()
            ->willReturn($contract->reveal());
        $restaurant
            ->getStripeAccount(false)
            ->willReturn($stripeAccount->reveal());

        $order
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

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

        $this->shouldSendStripeRequest('POST', '/v1/charges', [
            'amount' => 900,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
            'application_fee' => 250
        ]);

        $this->stripeManager->authorize($stripePayment);
    }

    public function testCaptureCapturesCharge()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');
        $stripePayment->setCharge('ch_123456');

        $this->shouldSendStripeRequest('GET', '/v1/charges/ch_123456');
        $this->shouldSendStripeRequest('POST', '/v1/charges/ch_123456/capture');

        $this->stripeManager->capture($stripePayment);
    }
}
