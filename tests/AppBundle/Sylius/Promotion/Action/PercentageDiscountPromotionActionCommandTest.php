<?php

namespace Tests\AppBundle\Sylius\Promotion\Action;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\Adjustment;

class PercentageDiscountPromotionActionCommandTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);

        $this->adjustmentFactory->createNew()
            ->willReturn(new Adjustment());

        $this->actionCommand = new PercentageDiscountPromotionActionCommand(
            $this->adjustmentFactory->reveal()
        );
    }

    public function testExecute()
    {
        $order = $this->prophesize(OrderInterface::class);

        $order->getItemsTotal()->willReturn(1000);

        $promotion = $this->prophesize(PromotionInterface::class);

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {

                $this->assertEquals(-150, $adjustment->getAmount());

                return true;
            }))
            ->shouldBeCalled();

        $this->assertTrue(
            $this->actionCommand->execute($order->reveal(), ['percentage' => 0.15], $promotion->reveal())
        );
    }

    public function testExecuteWith100PercentOnTotal()
    {
        $order = $this->prophesize(OrderInterface::class);

        $order->getItemsTotal()->willReturn(1000);
        $order->getTotal()->willReturn(1350);

        $promotion = $this->prophesize(PromotionInterface::class);

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {

                $this->assertEquals(-1350, $adjustment->getAmount());

                return true;
            }))
            ->shouldBeCalled();

        $this->assertTrue(
            $this->actionCommand->execute($order->reveal(), ['percentage' => 1.00, 'items_total' => false], $promotion->reveal())
        );
    }
}
