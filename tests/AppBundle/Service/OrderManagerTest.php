<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\StripeTransfer;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\StripeTransfer\StripeTransferTransitions;
use Doctrine\Common\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use SM\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\AppBundle\StripeTrait;

class OrderManagerTest extends TestCase
{
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $orderManager;

    public function setUp()
    {
        $this->setUpStripe();

        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->stateMachineFactory = $this->prophesize(StateMachineFactoryInterface::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

        $this->settingsManager
            ->get('stripe_secret_key')
            ->willReturn(self::$stripeApiKey);

        $this->orderManager = new OrderManager(
            $this->doctrine->reveal(),
            $this->routing->reveal(),
            $this->stateMachineFactory->reveal(),
            $this->settingsManager->reveal(),
            $this->eventDispatcher->reveal()
        );
    }

    public function testAuthorizePaymentDoesNothing()
    {
        $stripePayment = new StripePayment();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getLastPayment(PaymentInterface::STATE_NEW)
            ->willReturn($stripePayment);

        $this->stateMachineFactory
            ->get($stripePayment, PaymentTransitions::GRAPH)
            ->shouldNotBeCalled();

        $this->orderManager->authorizePayment($order->reveal());

        $this->assertEquals(PaymentInterface::STATE_CART, $stripePayment->getState());
    }

    public function testAuthorizePaymentCreateCharge()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_NEW);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');

        $stateMachine = $this->prophesize(StateMachineInterface::class);

        $stateMachine
            ->apply('authorize')
            ->shouldBeCalled();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getLastPayment(PaymentInterface::STATE_NEW)
            ->willReturn($stripePayment);
        $order
            ->getTotal()
            ->willReturn(900);
        $order
            ->getNumber()
            ->willReturn('000001');

        $this->stateMachineFactory
            ->get($stripePayment, PaymentTransitions::GRAPH)
            ->willReturn($stateMachine->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/charges', [
            'amount' => 900,
            'currency' => 'eur',
            'source' => 'tok_123456',
            'description' => 'Order 000001',
            'capture' => 'false',
        ]);

        $this->orderManager->authorizePayment($order->reveal());

        $this->assertNotNull($stripePayment->getCharge());
    }

    public function testCapturePaymentCapturesCharge()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_AUTHORIZED);
        $stripePayment->setStripeToken('tok_123456');
        $stripePayment->setCurrencyCode('EUR');
        $stripePayment->setCharge('ch_123456');

        $stateMachine = $this->prophesize(StateMachineInterface::class);

        $stateMachine
            ->apply(PaymentTransitions::TRANSITION_COMPLETE)
            ->shouldBeCalled();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getLastPayment(PaymentInterface::STATE_AUTHORIZED)
            ->willReturn($stripePayment);

        $this->stateMachineFactory
            ->get($stripePayment, PaymentTransitions::GRAPH)
            ->willReturn($stateMachine->reveal());

        $this->shouldSendStripeRequest('GET', '/v1/charges/ch_123456');
        $this->shouldSendStripeRequest('POST', '/v1/charges/ch_123456/capture');

        $this->orderManager->capturePayment($order->reveal());
    }

    public function testCreateDeliveryDoesNothing()
    {
        $delivery = new Delivery();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getDelivery()
            ->willReturn($delivery);

        $order
            ->setDelivery($delivery)
            ->shouldNotBeCalled();

        $this->orderManager->createDelivery($order->reveal());
    }

    public function testCreateTransferWithNoRestaurant()
    {
        $stripePayment = $this->prophesize(StripePayment::class);
        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getRestaurant()
            ->willReturn(null);

        $stripePayment
            ->getOrder()
            ->willReturn($order->reveal());

        $this->shouldNotSendStripeRequest();

        $this->orderManager->createTransfer($stripePayment->reveal());
    }

    public function testCreateTransferWithNoStripeAccount()
    {
        $stripePayment = $this->prophesize(StripePayment::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);

        $restaurant
            ->getStripeAccount()
            ->willReturn(null);

        $order
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $stripePayment
            ->getOrder()
            ->willReturn($order->reveal());

        $this->shouldNotSendStripeRequest();

        $this->orderManager->createTransfer($stripePayment->reveal());
    }

    public function testCreateTransfer()
    {
        $stripePayment = $this->prophesize(StripePayment::class);
        $stripeAccount = $this->prophesize(StripeAccount::class);
        $order = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(Restaurant::class);

        $stripeAccount
            ->getStripeUserId()
            ->willReturn('acct_123');

        $restaurant
            ->getStripeAccount()
            ->willReturn($stripeAccount->reveal());

        $order
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $order
            ->getTotal()
            ->willReturn(10000);
        $order
            ->getFeeTotal()
            ->willReturn(500);

        $stripePayment
            ->getOrder()
            ->willReturn($order->reveal());
        $stripePayment
            ->getCurrencyCode()
            ->willReturn('EUR');
        $stripePayment
            ->getCharge()
            ->willReturn('ch_123456');

        $stateMachine = $this->prophesize(StateMachineInterface::class);

        $stateMachine
            ->apply(StripeTransferTransitions::TRANSITION_COMPLETE)
            ->shouldBeCalled();

        $this->stateMachineFactory
            ->get(Argument::type(StripeTransfer::class), StripeTransferTransitions::GRAPH)
            ->willReturn($stateMachine->reveal());

        $this->shouldSendStripeRequest('POST', '/v1/transfers', [
            'amount' => 9500,
            'currency' => 'eur',
            'destination' => 'acct_123',
            'source_transaction' => 'ch_123456',
        ]);

        $this->orderManager->createTransfer($stripePayment->reveal());
    }
}
