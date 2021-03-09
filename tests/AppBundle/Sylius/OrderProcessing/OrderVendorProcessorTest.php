<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\OrderProcessing\OrderVendorProcessor;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderVendorProcessorTest extends KernelTestCase
{
    use ProphecyTrait;

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

        $this->orderProcessor = new OrderVendorProcessor(
            $this->adjustmentFactory,
            $this->translator->reveal(),
            new NullLogger()
        );
    }

    private function createRestaurant($originCode, $stripeUserId = null, $paysStripeFee = true)
    {
        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant->asOriginCode()->willReturn($originCode);

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

        return $restaurant->reveal();
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

    public function testProcessWithHub()
    {
        $restaurant1 = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true);
        $restaurant3 = $this->createRestaurant('3', 'acct_789', $paysStripeFee = true);
        $restaurant4 = $this->createRestaurant('4', 'acct_987', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2, $restaurant3, $restaurant4 ]);

        $vendor = new Vendor();
        $vendor->setHub($hub->reveal());

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getState()
            ->willReturn(OrderInterface::STATE_CART);
        $order
            ->getTotal()
            ->willReturn(4700);
        $order
            ->getFeeTotal()
            ->willReturn(740);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getVendor()
            ->willReturn($vendor);
        $order
            ->getVendors()
            ->willReturn([ $restaurant1, $restaurant2, $restaurant3 ]);

        // Total = 47.00
        // Items = 40.00
        // Fees  =  7.40
        // Rest  = 39.60

        // Vendor 1 = 3960 * 0.6750 = 2673
        // Vendor 2 = 3960 * 0.1750 =  693
        // Vendor 3 = 3960 * 0.1500 =  594

        $hub
            ->getPercentageForRestaurant(
                $order->reveal(),
                Argument::type(LocalBusiness::class)
            )
            ->will(function ($args) use ($restaurant1, $restaurant2, $restaurant3) {
                if ($args[1] === $restaurant1) {
                    return 0.6750;
                }
                if ($args[1] === $restaurant2) {
                    return 0.1750;
                }
                if ($args[1] === $restaurant3) {
                    return 0.1500;
                }
            });

        $payment = new Payment();
        $payment->setAmount(4700);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');
        $payment->setOrder($order->reveal());

        $order
            ->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT)
            ->shouldBeCalled();

        $expectations = [
            '1' => 2673,
            '2' =>  693,
            '3' =>  594,
        ];

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) use ($expectations) {
                if (AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT === $adjustment->getType()) {
                    return isset($expectations[$adjustment->getOriginCode()]) &&
                        $expectations[$adjustment->getOriginCode()] === $adjustment->getAmount();
                }

                return false;
            }))
            ->shouldBeCalledTimes(3);

        $this->orderProcessor->process($order->reveal());
    }
}
