<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Hub;
use AppBundle\Entity\HubRepository;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\OrderProcessing\OrderVendorProcessor;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Model\Adjustment;
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

        $this->hubRepository = $this->prophesize(HubRepository::class);
        $this->localBusinessRepository = $this->prophesize(LocalBusinessRepository::class);

        $this->orderProcessor = new OrderVendorProcessor(
            $this->hubRepository->reveal(),
            $this->localBusinessRepository->reveal(),
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

    private function createOrderItem(ProductInterface $product)
    {
        $item = $this->prophesize(OrderItemInterface::class);
        $variant = $this->prophesize(ProductVariantInterface::class);

        $variant
            ->getProduct()
            ->willReturn($product);
        $item
            ->getVariant()
            ->willReturn($variant);

        return $item->reveal();
    }

    public function testProcessDoesNothingWithNoVendor()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getState()
            ->willReturn(OrderInterface::STATE_CART);
        $order
            ->hasVendor()
            ->willReturn(false);

        $order
            ->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT)
            ->shouldNotBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessDoesNothingWithNotCart()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getState()
            ->willReturn(OrderInterface::STATE_NEW);
        $order
            ->hasVendor()
            ->willReturn(true);

        $order
            ->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT)
            ->shouldNotBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessDoesNothingWithSameVendor()
    {
        $restaurant      = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true);
        $otherRestaurant = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true);

        $product1 = $this->prophesize(ProductInterface::class);
        $product2 = $this->prophesize(ProductInterface::class);

        $vendor = Vendor::withRestaurant($restaurant);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getState()
            ->willReturn(OrderInterface::STATE_CART);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getVendor()
            ->willReturn($vendor);

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        $this->localBusinessRepository
            ->findOneByProduct(
                Argument::type(ProductInterface::class)
            )
            ->willReturn($restaurant);

        $order
            ->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT)
            ->shouldBeCalled();

        $order->setVendor(Argument::that(function (Vendor $vnd) use ($vendor) {
            return $vendor === $vnd;
        }))->shouldBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessDowngradesVendorWithOtherRestaurant()
    {
        $restaurant      = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true);
        $otherRestaurant = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true);

        $product1 = $this->prophesize(ProductInterface::class);
        $product2 = $this->prophesize(ProductInterface::class);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getState()
            ->willReturn(OrderInterface::STATE_CART);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($otherRestaurant)
            );

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        $this->localBusinessRepository
            ->findOneByProduct(
                Argument::type(ProductInterface::class)
            )
            ->willReturn($restaurant);

        $order
            ->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT)
            ->shouldBeCalled();

        $order->setVendor(Argument::that(function (Vendor $vendor) use ($restaurant) {
            return $vendor->getRestaurant() === $restaurant;
        }))->shouldBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessDowngradesVendorWithHub()
    {
        $restaurant      = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true);
        $otherRestaurant = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true);

        $product1 = $this->prophesize(ProductInterface::class);
        $product2 = $this->prophesize(ProductInterface::class);

        $hub = new Hub();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getState()
            ->willReturn(OrderInterface::STATE_CART);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withHub($hub)
            );

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        $this->localBusinessRepository
            ->findOneByProduct(
                Argument::type(ProductInterface::class)
            )
            ->willReturn($restaurant);

        $order
            ->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT)
            ->shouldBeCalled();

        $order->setVendor(Argument::that(function (Vendor $vendor) use ($restaurant) {
            return $vendor->getRestaurant() === $restaurant;
        }))->shouldBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessUpgradesVendorAndAddsAdjustments()
    {
        $restaurant1 = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true);
        $restaurant2 = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true);
        $restaurant3 = $this->createRestaurant('3', 'acct_789', $paysStripeFee = true);
        $restaurant4 = $this->createRestaurant('4', 'acct_987', $paysStripeFee = true);

        $hub = $this->prophesize(Hub::class);
        $hub
            ->getRestaurants()
            ->willReturn([ $restaurant1, $restaurant2, $restaurant3, $restaurant4 ]);

        $vendor = Vendor::withHub($hub->reveal());

        $product1 = $this->prophesize(ProductInterface::class)->reveal();
        $product2 = $this->prophesize(ProductInterface::class)->reveal();
        $product3 = $this->prophesize(ProductInterface::class)->reveal();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getId()
            ->willReturn(1);
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
        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1),
                $this->createOrderItem($product2),
                $this->createOrderItem($product3),
            ]));

        $this->localBusinessRepository
            ->findOneByProduct(
                Argument::type(ProductInterface::class)
            )
            ->will(function ($args) use ($product1, $product2, $product3, $restaurant1, $restaurant2, $restaurant3) {
                if ($args[0] === $product1) {
                    return $restaurant1;
                }
                if ($args[0] === $product2) {
                    return $restaurant2;
                }
                if ($args[0] === $product3) {
                    return $restaurant3;
                }
            });

        $this->hubRepository
            ->findOneByRestaurant(Argument::type(LocalBusiness::class))
            ->willReturn($hub);

        // Total = 47.00
        // Items = 40.00
        // Fees  =  7.40
        // Rest  = 39.60

        // Vendor 1 = 3960 * 0.6750 = 2673
        // Vendor 2 = 3960 * 0.1750 =  693
        // Vendor 3 = 3960 * 0.1500 =  594

        $order
            ->getPercentageForRestaurant(Argument::type(LocalBusiness::class))
            ->will(function ($args) use ($restaurant1, $restaurant2, $restaurant3) {
                if ($args[0] === $restaurant1) {
                    return 0.6750;
                }
                if ($args[0] === $restaurant2) {
                    return 0.1750;
                }
                if ($args[0] === $restaurant3) {
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

        $order->setVendor(Argument::that(function (Vendor $vnd) use ($vendor) {
            return $vendor === $vnd;
        }))->shouldBeCalled();

        $this->orderProcessor->process($order->reveal());
    }
}
