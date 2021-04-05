<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
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

        $this->orderDepositRefundProcessor = new OrderDepositRefundProcessor(
            $this->adjustmentFactory->reveal(),
            $this->translator->reveal()
        );
    }

    private static function createContract($flatDeliveryPrice, $customerAmount, $feeRate)
    {
        $contract = new Contract();
        $contract->setFlatDeliveryPrice($flatDeliveryPrice);
        $contract->setCustomerAmount($customerAmount);
        $contract->setFeeRate($feeRate);

        return $contract;
    }

    private function createOrderItem(LocalBusiness $restaurant, ReusablePackaging $reusablePackaging, $quantity, $units, $enabled)
    {
        $orderItem = $this->prophesize(OrderItemInterface::class);
        $variant = $this->prophesize(ProductVariant::class);

        $product = new Product();
        $product->setReusablePackagingEnabled($enabled);
        $product->setReusablePackagingUnit($units);

        $variant->getProduct()->willReturn($product);
        $orderItem->getVariant()->willReturn($variant->reveal());
        $orderItem->getQuantity()->willReturn($quantity);

        $restaurant->addProduct($product);
        $product->setReusablePackaging($reusablePackaging);

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
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant));
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

        $item1 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 1, $units = 0.5, $enabled = true);
        $item1
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {
                return $adjustment->getAmount() === 100;
            }))
            ->shouldBeCalled();

        $item2 = $this->createOrderItem($restaurant, $reusablePackaging, $quantity = 2, $units = 1, $enabled = true);
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
}
