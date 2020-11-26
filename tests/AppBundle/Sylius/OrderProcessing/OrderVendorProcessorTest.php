<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use ApiPlatform\Core\Api\IriConverterInterface;
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
use Symfony\Component\Translation\TranslatorInterface;

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

        $this->iriConverter = $this->prophesize(IriConverterInterface::class);

        $this->orderProcessor = new OrderVendorProcessor(
            $this->adjustmentFactory,
            $this->translator->reveal(),
            $this->iriConverter->reveal(),
            new NullLogger()
        );
    }

    private function createRestaurant($stripeUserId = null, $paysStripeFee = true)
    {
        $restaurant = $this->prophesize(LocalBusiness::class);

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
        $restaurant1 = $this->createRestaurant('acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('acct_456', $paysStripeFee = true);
        $restaurant3 = $this->createRestaurant('acct_789', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2, $restaurant3 ]);

        $vendor = new Vendor();
        $vendor->setHub($hub->reveal());

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getNumber()
            ->willReturn('000001');
        $order
            ->getTotal()
            ->willReturn(3000);
        $order
            ->getFeeTotal()
            ->willReturn(750);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getVendor()
            ->willReturn($vendor);
        $order
            ->getVendors()
            ->willReturn([ $restaurant1, $restaurant2 ]);

        // Total = 30.00
        // Items = 22.50
        // Fees  =  7.50

        // Resto 1 = 1700 - (750 * 0.76) = 1130
        // Resto 2 =  550 - (750 * 0.24) = 370

        $hub
            ->getPercentageForRestaurant(
                $order->reveal(),
                Argument::type(LocalBusiness::class)
            )
            ->will(function ($args) use ($restaurant1, $restaurant2) {
                if ($args[1] === $restaurant1) {
                    return 0.76;
                }
                if ($args[1] === $restaurant2) {
                    return 0.24;
                }
            });
        $hub
            ->getItemsTotalForRestaurant(
                $order->reveal(),
                Argument::type(LocalBusiness::class)
            )
            ->will(function ($args) use ($restaurant1, $restaurant2) {
                if ($args[1] === $restaurant1) {
                    return 1700;
                }
                if ($args[1] === $restaurant2) {
                    return 550;
                }
            });

        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setStripeToken('tok_123456');
        $payment->setCurrencyCode('EUR');
        $payment->setCharge('ch_123456');
        $payment->setOrder($order->reveal());

        $this->iriConverter
            ->getIriFromItem(Argument::type(LocalBusiness::class))
            ->will(function ($args) use ($restaurant1, $restaurant2) {
                if ($args[0] === $restaurant1) {
                    return '/api/restaurants/1';
                }
                if ($args[0] === $restaurant2) {
                    return '/api/restaurants/2';
                }
            });

        $order
            ->removeAdjustments(Argument::that(function (string $type) {
                return in_array($type, [
                    AdjustmentInterface::VENDOR_FEE_ADJUSTMENT,
                    AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT,
                ]);
            }))
            ->shouldBeCalledTimes(2);

        $order
            ->addAdjustment(Argument::that(function (Adjustment $adjustment) {

                if (AdjustmentInterface::VENDOR_FEE_ADJUSTMENT === $adjustment->getType()) {
                    if ('/api/restaurants/1' === $adjustment->getOriginCode()) {
                        return 570 === $adjustment->getAmount();
                    }
                    if ('/api/restaurants/2' === $adjustment->getOriginCode()) {
                        return 180 === $adjustment->getAmount();
                    }
                }

                if (AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT === $adjustment->getType()) {
                    if ('/api/restaurants/1' === $adjustment->getOriginCode()) {
                        return 1130 === $adjustment->getAmount();
                    }
                    if ('/api/restaurants/2' === $adjustment->getOriginCode()) {
                        return 370 === $adjustment->getAmount();
                    }
                }

                return true;
            }))
            ->shouldBeCalledTimes(4);

        $this->orderProcessor->process($order->reveal());
    }
}
