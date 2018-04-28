<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
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

        // TODO Add assertions for Stripe requests

        $this->orderManager->authorizePayment($order->reveal());

        $this->assertNotNull($stripePayment->getCharge());
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
}
