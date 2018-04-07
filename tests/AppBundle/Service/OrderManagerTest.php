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
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderManagerTest extends TestCase
{
    private $orderManager;

    public function setUp()
    {
        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->stateMachineFactory = $this->prophesize(StateMachineFactoryInterface::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

        $this->settingsManager
            ->get('stripe_secret_key')
            ->willReturn('sk_1234567890');

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
            ->getLastPayment(PaymentInterface::STATE_CART)
            ->willReturn($stripePayment);

        $this->stateMachineFactory
            ->get($stripePayment, PaymentTransitions::GRAPH)
            ->shouldNotBeCalled();

        $this->orderManager->authorizePayment($order->reveal());

        $this->assertEquals(PaymentInterface::STATE_CART, $stripePayment->getState());
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
