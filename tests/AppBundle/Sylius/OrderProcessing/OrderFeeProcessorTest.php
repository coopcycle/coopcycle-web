<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\OrderProcessing\OrderFeeProcessor;
use Prophecy\Argument;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorInterface;

class OrderFeeProcessorTest extends KernelTestCase
{
    private $adjustmentFactory;
    private $orderFeeProcessor;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->translator
            ->trans(Argument::type('string'))
            ->willReturn('Foo');

        $this->adjustmentFactory = static::$kernel->getContainer()->get('sylius.factory.adjustment');
        $this->orderFeeProcessor = new OrderFeeProcessor($this->adjustmentFactory, $this->translator->reveal());
    }

    private static function createContract($flatDeliveryPrice, $customerAmount, $feeRate)
    {
        $contract = new Contract();
        $contract->setFlatDeliveryPrice($flatDeliveryPrice);
        $contract->setCustomerAmount($customerAmount);
        $contract->setFeeRate($feeRate);

        return $contract;
    }

    private function createOrderItem($total)
    {
        $orderItem = $this->prophesize(OrderItemInterface::class);
        $orderItem
            ->getTotal()
            ->willReturn($total);

        $orderItem
            ->setOrder(Argument::type(OrderInterface::class))
            ->shouldBeCalled();

        return $orderItem->reveal();
    }

    public function testOrderWithoutRestaurant()
    {
        $order = new Order();
        $order->addItem($this->createOrderItem(100));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(0, $adjustments);
        $this->assertEquals(0, $order->getFeeTotal());
    }

    public function testOrderWithoutRestaurantFees()
    {
        $contract = self::createContract(0, 0, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(0, $order->getFeeTotal());
    }

    public function testOrderWithRestaurantFees()
    {
        $contract = self::createContract(0, 0, 0.25);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(250, $order->getFeeTotal());
    }

    public function testOrderWithBusinessAmount()
    {
        $contract = self::createContract(500, 0, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(500, $order->getFeeTotal());
    }

    public function testOrderWithBusinessAmountAndCustomerAmount()
    {
        $contract = self::createContract(500, 500, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $this->assertEquals(1500, $order->getTotal());

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(500, $order->getFeeTotal());
    }
}
