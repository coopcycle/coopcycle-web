<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\OrderProcessing\OrderDepositRefundProcessor;
use AppBundle\Entity\Sylius\ProductVariant;
use Prophecy\Argument;
use Sylius\Component\Order\Model\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatorInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use AppBundle\Entity\Sylius\Product;

class OrderDepositRefundProcessorTest extends TestCase
{
    private $adjustmentFactory;
    private $orderDepositRefundProcessor;

    public function setUp(): void
    {
        $this->adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);
        $this->orderDepositRefundProcessor = new OrderDepositRefundProcessor($this->adjustmentFactory->reveal());
    }

    private static function createContract($flatDeliveryPrice, $customerAmount, $feeRate)
    {
        $contract = new Contract();
        $contract->setFlatDeliveryPrice($flatDeliveryPrice);
        $contract->setCustomerAmount($customerAmount);
        $contract->setFeeRate($feeRate);

        return $contract;
    }

    private function createOrderItem($units, $enabled)
    {
        $orderItem = $this->prophesize(OrderItemInterface::class);
        $variant = $this->prophesize(ProductVariant::class);

        $product = new Product();
        $product->setReusablePackagingEnabled($enabled);
        // $orderItem->setReusablePackagingEnabled($enabled);
        $product->setReusablePackagingUnit($units);
        $variant->getProduct()->willReturn($product);
        $orderItem->getVariant()->willReturn($variant->reveal());
        // $orderItem
        //     ->getTotal()
        //     ->willReturn($total);

        // $orderItem
        //     ->setOrder(Argument::type(OrderInterface::class))
        //     ->shouldBeCalled();
        return $orderItem->reveal();
    }

    public function testRestaurantDepositRefundDisabledDoesNothing()
    {
        $order = new Order();

        $restaurant = new Restaurant();
        $restaurant->setDepositRefundEnabled(false);

        $order->setRestaurant($restaurant);

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('int'),
            Argument::type('bool')
        )->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order);
    }

    public function testOrderDoesNotContainReusablePackagingDoesNothing()
    {
        $order = new Order();
        $restaurant = new Restaurant();

        $restaurant->setDepositRefundEnabled(true);
        $order->setRestaurant($restaurant);

        $order->setReusablePackagingEnabled(false);

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('int'),
            Argument::type('bool')
        )->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order);
    }

    public function testOrderDepositRefundEnabledAddAdjustment()
    {
        $restaurant = new Restaurant();
        $restaurant->setDepositRefundEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->removeAdjustments(AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT)
            ->shouldBeCalled();

        $items = new ArrayCollection([
            $this->createOrderItem($units = 1, $enabled = true),
            $this->createOrderItem($units = 1, $enabled = true)
        ]);
        $order->getItems()->willReturn($items);

        $adjustment = $this->prophesize(AdjustmentInterface::class)->reveal();

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT,
            Argument::type('string'),
            200,
            Argument::type('bool')
        )->willReturn($adjustment);

        $order->addAdjustment($adjustment)->shouldBeCalled();

        $this->orderDepositRefundProcessor->process($order->reveal());
    }

    //     public function testOrderDepositRefundEnabledAddAdjustment()
    // {
    //     $restaurant = new Restaurant();
    //     $restaurant->setDepositRefundEnabled(true);

    //     $order = $this->prophesize(Order::class);
    //     $order->getRestaurant()->willReturn($restaurant);
    //     $order->removeAdjustments(AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT)->shouldBeCalled();

    //     $items = new ArrayCollection([
    //         $this->createOrderItem($units = 1, true),
    //         $this->createOrderItem($units = 1, true)
    //     ]);
    //     $order->getItems()->willReturn($items);
    //     // $order->getItems()->getVariant()->getProduct()->getReusablePackagingEnabled()->shouldBeCalled();
    //     // $order->getItems()->getVariant()->getProduct()->getReusablePackagingUnit()->shouldBeCalled();

    //     $adjustment = $this->prophesize(AdjustmentInterface::class)->reveal();

    //     $this->adjustmentFactory->createWithData(
    //         AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT,
    //         Argument::type('string'),
    //         200,
    //         Argument::type('bool')
    //     )->willReturn($adjustment);

    //     $order->addAdjustment($adjustment)->shouldBeCalled();

    //     $this->orderDepositRefundProcessor->process($order->reveal());
    // }

    // public function testOrderWithoutRestaurantFees()
    // {
    //     $contract = self::createContract(0, 0, 0.00);

    //     $restaurant = new Restaurant();
    //     $restaurant->setContract($contract);

    //     $order = new Order();
    //     $order->setRestaurant($restaurant);
    //     $order->addItem($this->createOrderItem(1000));

    //     $this->orderFeeProcessor->process($order);

    //     $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

    //     $this->assertCount(1, $adjustments);
    //     $this->assertEquals(0, $order->getFeeTotal());
    // }

    // public function testOrderWithRestaurantFees()
    // {
    //     $contract = self::createContract(0, 0, 0.25);

    //     $restaurant = new Restaurant();
    //     $restaurant->setContract($contract);

    //     $order = new Order();
    //     $order->setRestaurant($restaurant);
    //     $order->addItem($this->createOrderItem(1000));

    //     $this->orderFeeProcessor->process($order);

    //     $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

    //     $this->assertCount(1, $adjustments);
    //     $this->assertEquals(250, $order->getFeeTotal());
    // }

    // public function testOrderWithBusinessAmount()
    // {
    //     $contract = self::createContract(500, 0, 0.00);

    //     $restaurant = new Restaurant();
    //     $restaurant->setContract($contract);

    //     $order = new Order();
    //     $order->setRestaurant($restaurant);
    //     $order->addItem($this->createOrderItem(1000));

    //     $this->orderFeeProcessor->process($order);

    //     $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

    //     $this->assertCount(1, $adjustments);
    //     $this->assertEquals(500, $order->getFeeTotal());
    // }

    // public function testOrderWithBusinessAmountAndCustomerAmount()
    // {
    //     $contract = self::createContract(500, 500, 0.00);

    //     $restaurant = new Restaurant();
    //     $restaurant->setContract($contract);

    //     $order = new Order();
    //     $order->setRestaurant($restaurant);
    //     $order->addItem($this->createOrderItem(1000));

    //     $this->orderFeeProcessor->process($order);

    //     $this->assertEquals(1500, $order->getTotal());

    //     $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

    //     $this->assertCount(1, $adjustments);
    //     $this->assertEquals(500, $order->getFeeTotal());
    // }
}
