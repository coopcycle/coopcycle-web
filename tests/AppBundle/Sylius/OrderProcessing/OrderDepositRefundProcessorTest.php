<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\ReusablePackagings;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\OrderProcessing\OrderDepositRefundProcessor;
use AppBundle\Entity\Sylius\ProductVariant;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Order\Model\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use AppBundle\Entity\Sylius\Product;

class OrderDepositRefundProcessorTest extends TestCase
{
    use ProphecyTrait;

    private $adjustmentFactory;
    private $orderDepositRefundProcessor;

    public function setUp(): void
    {
        $this->adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->translator->trans('order.adjustment_type.reusable_packaging')
            ->willReturn('Packaging');

        $this->translator->trans('order_item.adjustment_type.reusable_packaging', Argument::type('array'))
            ->willReturn('1 × packaging(s)');

        $this->translator->trans('order.adjustment_type.reusable_packaging.loopeat')
            ->willReturn('Loopeat processing fee');

        $this->orderDepositRefundProcessor = new OrderDepositRefundProcessor(
            $this->adjustmentFactory->reveal(),
            $this->translator->reveal(),
            $loopeatProcessingFee = 200
        );
    }

    private function createOrderItem(LocalBusiness $restaurant, ReusablePackaging $reusablePackaging, $quantity, $units, $enabled, $id)
    {
        $orderItem = $this->prophesize(OrderItemInterface::class);
        $variant = $this->prophesize(ProductVariant::class);

        $product = new Product();
        $product->setReusablePackagingEnabled($enabled);

        $reusablePackagings = new ReusablePackagings();
        $reusablePackagings->setReusablePackaging($reusablePackaging);
        $reusablePackagings->setUnits($units);

        $product->addReusablePackaging($reusablePackagings);

        $variant->getProduct()->willReturn($product);
        $orderItem->getVariant()->willReturn($variant->reveal());
        $orderItem->getQuantity()->willReturn($quantity);
        $orderItem->getId()->willReturn($id);

        $restaurant->addProduct($product);

        return $orderItem;
    }

    public function testNoRestaurantDoesNothing()
    {
        $order = new Order();

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('int'),
            Argument::type('bool')
        )->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order);
    }

    public function testRestaurantDepositRefundDisabledDoesNothing()
    {
        $order = new Order();

        $restaurant = new LocalBusiness();
        $restaurant->setDepositRefundEnabled(false);

        $order->setRestaurant($restaurant);

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('int'),
            Argument::type('bool')
        )->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order);
    }

    public function testOrderDoesNotContainReusablePackagingDoesNothing()
    {
        $order = new Order();
        $restaurant = new LocalBusiness();

        $restaurant->setDepositRefundEnabled(true);
        $order->setRestaurant($restaurant);

        $order->setReusablePackagingEnabled(false);

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('int'),
            Argument::type('bool')
        )->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order);
    }

    public function testOrderDepositRefundEnabledAddAdjustment()
    {
        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setPrice(100);

        $restaurant = new LocalBusiness();
        $restaurant->setDepositRefundEnabled(true);
        $restaurant->addReusablePackaging($reusablePackaging);

        $order = $this->prophesize(Order::class);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
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
            ->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)
            ->shouldBeCalled();

        $adjustment = $this->prophesize(AdjustmentInterface::class)->reveal();

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('integer'),
            Argument::type('bool')
        )
            ->shouldBeCalled()
            ->will(function ($args) {
                $adjustment = new Adjustment();
                $adjustment->setType($args[0]);
                $adjustment->setAmount($args[2]);

                return $adjustment;
            });

        $item1 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 1, $units = 0.5, $enabled = true, $id = 1);
        $item1
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 100;
            }))
            ->shouldBeCalled();

        $item2 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 2, $units = 1, $enabled = true, $id = 2);
        $item2
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 200;
            }))
            ->shouldBeCalled();

        $items = new ArrayCollection([ $item1->reveal(), $item2->reveal() ]);
        $order->getItems()->willReturn($items);

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 300;
            }))
            ->shouldBeCalled();

        $this->orderDepositRefundProcessor->process($order->reveal());
    }

    public function testRestaurantDepositRefundDisabledDoesNothingWithOptinFalse()
    {
        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setPrice(100);

        $restaurant = new LocalBusiness();
        $restaurant->setDepositRefundEnabled(false);
        $restaurant->setDepositRefundOptin(false);
        $restaurant->addReusablePackaging($reusablePackaging);

        $order = $this->prophesize(Order::class);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
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
            ->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)
            ->shouldBeCalled();

        $item1 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 1, $units = 0.5, $enabled = true, $id = 1);
        $items = new ArrayCollection([ $item1->reveal() ]);
        $order->getItems()->willReturn($items);

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('int'),
            Argument::type('bool')
        )->shouldNotBeCalled();

        $order
            ->addAdjustment(
                Argument::type(AdjustmentInterface::class)
            )
            ->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order->reveal());
    }

    public function testLoopeatDeliverOverridesQuantity()
    {
        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setPrice(0);
        $reusablePackaging->setData(['id' => 1]);
        $reusablePackaging->setType(reusablePackaging::TYPE_LOOPEAT);
        $reusablePackaging->setName('Small box');

        $restaurant = new LocalBusiness();
        $restaurant->setDepositRefundEnabled(true);
        $restaurant->addReusablePackaging($reusablePackaging);
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
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
            ->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)
            ->shouldBeCalled();
        $order
            ->getLoopeatDeliver()
            ->willReturn([
                2 => [
                    ['format_id' => 1, 'quantity' => 2]
                ]
            ]);

        $adjustment = $this->prophesize(AdjustmentInterface::class)->reveal();

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('integer'),
            Argument::type('bool')
        )
            ->shouldBeCalled()
            ->will(function ($args) {
                $adjustment = new Adjustment();
                $adjustment->setType($args[0]);
                $adjustment->setLabel($args[1]);
                $adjustment->setAmount($args[2]);

                return $adjustment;
            });

        $item1 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 1, $units = 1, $enabled = true, $id = 1);
        $item1
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 0;
            }))
            ->shouldBeCalled();

        $item2 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 2, $units = 3, $enabled = true, $id = 2);
        $item2
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 0 && '2 × Small box' === $adjustment->getLabel();
            }))
            ->shouldBeCalled();

        $items = new ArrayCollection([ $item1->reveal(), $item2->reveal() ]);
        $order->getItems()->willReturn($items);

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 200;
            }))
            ->shouldBeCalled();

        $this->orderDepositRefundProcessor->process($order->reveal());
    }

    public function testLoopeatProcessingFeeOnReturnsWithoutReturns()
    {
        $this->orderDepositRefundProcessor
            ->setLoopeatProcessingFeeBehavior(OrderDepositRefundProcessor::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ON_RETURNS);

        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setPrice(0);
        $reusablePackaging->setData(['id' => 1]);
        $reusablePackaging->setType(reusablePackaging::TYPE_LOOPEAT);
        $reusablePackaging->setName('Small box');

        $restaurant = new LocalBusiness();
        $restaurant->setDepositRefundEnabled(true);
        $restaurant->addReusablePackaging($reusablePackaging);
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
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
            ->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)
            ->shouldBeCalled();
        $order
            ->getLoopeatDeliver()
            ->willReturn([
                1 => [
                    ['format_id' => 1, 'quantity' => 3]
                ]
            ]);
        $order
            ->hasLoopeatReturns()
            ->willReturn(false);

        $adjustment = $this->prophesize(AdjustmentInterface::class)->reveal();

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('integer'),
            Argument::type('bool')
        )
            ->shouldBeCalled()
            ->will(function ($args) {
                $adjustment = new Adjustment();
                $adjustment->setType($args[0]);
                $adjustment->setAmount($args[2]);

                return $adjustment;
            });

        $item1 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 1, $units = 0.5, $enabled = true, $id = 1);
        $item1
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 0;
            }))
            ->shouldBeCalled();

        $item2 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 2, $units = 1, $enabled = true, $id = 2);
        $item2
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 0;
            }))
            ->shouldBeCalled();

        $items = new ArrayCollection([ $item1->reveal(), $item2->reveal() ]);
        $order->getItems()->willReturn($items);

        $order
            ->addAdjustment(Argument::type(Adjustment::class))
            ->shouldNotBeCalled();

        $this->orderDepositRefundProcessor->process($order->reveal());
    }

    public function testLoopeatProcessingFeeOnReturnsWithReturns()
    {
        $this->orderDepositRefundProcessor
            ->setLoopeatProcessingFeeBehavior(OrderDepositRefundProcessor::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ON_RETURNS);

        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setPrice(0);
        $reusablePackaging->setData(['id' => 1]);
        $reusablePackaging->setType(reusablePackaging::TYPE_LOOPEAT);
        $reusablePackaging->setName('Small box');

        $restaurant = new LocalBusiness();
        $restaurant->setDepositRefundEnabled(true);
        $restaurant->addReusablePackaging($reusablePackaging);
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
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
            ->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)
            ->shouldBeCalled();
        $order
            ->getLoopeatDeliver()
            ->willReturn([
                1 => [
                    ['format_id' => 1, 'quantity' => 3]
                ]
            ]);
        $order
            ->hasLoopeatReturns()
            ->willReturn(true);

        $adjustment = $this->prophesize(AdjustmentInterface::class)->reveal();

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            Argument::type('string'),
            Argument::type('integer'),
            Argument::type('bool')
        )
            ->shouldBeCalled()
            ->will(function ($args) {
                $adjustment = new Adjustment();
                $adjustment->setType($args[0]);
                $adjustment->setAmount($args[2]);

                return $adjustment;
            });

        $item1 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 1, $units = 0.5, $enabled = true, $id = 1);
        $item1
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 0;
            }))
            ->shouldBeCalled();

        $item2 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 2, $units = 1, $enabled = true, $id = 2);
        $item2
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 0;
            }))
            ->shouldBeCalled();

        $items = new ArrayCollection([ $item1->reveal(), $item2->reveal() ]);
        $order->getItems()->willReturn($items);

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 200;
            }))
            ->shouldBeCalled();

        $this->orderDepositRefundProcessor->process($order->reveal());
    }
}
