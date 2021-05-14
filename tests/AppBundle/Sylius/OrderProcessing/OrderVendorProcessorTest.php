<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\OrderProcessing\OrderVendorProcessor;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderVendorProcessorTest extends TestCase
{
    use ProphecyTrait;

    private $adjustmentFactory;
    private $orderFeeProcessor;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->orderProcessor = new OrderVendorProcessor(
            $this->entityManager->reveal(),
            new NullLogger()
        );
    }

    private function createRestaurant($originCode, $stripeUserId = null, $paysStripeFee = true, ?Hub $hub = null)
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

        if (null !== $hub) {
            $restaurant->belongsToHub()->willReturn(true);
            $restaurant->getHub()->willReturn($hub);
        } else {
            $restaurant->belongsToHub()->willReturn(false);
        }

        return $restaurant->reveal();
    }

    private function createOrderItem(ProductInterface $product, int $total = 0)
    {
        $item = $this->prophesize(OrderItemInterface::class);
        $variant = $this->prophesize(ProductVariantInterface::class);

        $variant
            ->getProduct()
            ->willReturn($product);
        $item
            ->getVariant()
            ->willReturn($variant);
        $item
            ->getTotal()
            ->willReturn($total);

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
            ->getVendors()
            ->willReturn(new ArrayCollection());

        $order
            ->addRestaurant(Argument::type(LocalBusiness::class))
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
            ->addRestaurant(Argument::type(LocalBusiness::class))
            ->shouldNotBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessDoesNothingWithSameVendor()
    {
        $restaurant      = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true);
        $otherRestaurant = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true);

        $product1 = $this->prophesize(ProductInterface::class);
        $product1->getRestaurant()->willReturn($restaurant);

        $product2 = $this->prophesize(ProductInterface::class);
        $product2->getRestaurant()->willReturn($restaurant);

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
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getVendor()
            ->willReturn($vendor);
        $order
            ->getVendors()
            ->willReturn(new ArrayCollection([
                new OrderVendor($order->reveal(), $restaurant, 2000, 0)
            ]));
        $order
            ->getTotal()
            ->willReturn(2000);
        $order
            ->getFeeTotal()
            ->willReturn(300);
        $order
            ->getPercentageForRestaurant($restaurant)
            ->willReturn(1.0);

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        $order
            ->addRestaurant($restaurant, 0, 1700)
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
        $product1->getRestaurant()->willReturn($restaurant);

        $product2 = $this->prophesize(ProductInterface::class);
        $product2->getRestaurant()->willReturn($restaurant);

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
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($otherRestaurant)
            );
        $order
            ->getVendors()
            ->willReturn(new ArrayCollection([
                new OrderVendor($order->reveal(), $otherRestaurant, 2000, 0)
            ]));
        $order
            ->getTotal()
            ->willReturn(2000);
        $order
            ->getFeeTotal()
            ->willReturn(300);
        $order
            ->getPercentageForRestaurant($restaurant)
            ->willReturn(1.0);
        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        $order
            ->addRestaurant($restaurant, 0, 1700)
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
        $product1->getRestaurant()->willReturn($restaurant);

        $product2 = $this->prophesize(ProductInterface::class);
        $product2->getRestaurant()->willReturn($restaurant);

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
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withHub($hub)
            );
        $order
            ->getVendors()
            ->willReturn(new ArrayCollection([
                new OrderVendor($order->reveal(), $restaurant, 1000, 0),
                new OrderVendor($order->reveal(), $otherRestaurant, 1000, 0)
            ]));
        $order
            ->getTotal()
            ->willReturn(2000);
        $order
            ->getFeeTotal()
            ->willReturn(300);
        $order
            ->getPercentageForRestaurant(Argument::type(LocalBusiness::class))
            ->willReturn(0.5);

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        $order
            ->addRestaurant($restaurant, 0, 850)
            ->shouldBeCalled();

        $order->setVendor(Argument::that(function (Vendor $vendor) use ($restaurant) {
            return $vendor->getRestaurant() === $restaurant;
        }))->shouldBeCalled();

        $this->orderProcessor->process($order->reveal());
    }

    public function testProcessUpgradesVendorAndAddsAdjustments()
    {
        $hub = $this->prophesize(Hub::class);

        $restaurant1 = $this->createRestaurant('1', 'acct_123', $paysStripeFee = true, $hub->reveal());
        $restaurant2 = $this->createRestaurant('2', 'acct_456', $paysStripeFee = true, $hub->reveal());
        $restaurant3 = $this->createRestaurant('3', 'acct_789', $paysStripeFee = true, $hub->reveal());
        $restaurant4 = $this->createRestaurant('4', 'acct_987', $paysStripeFee = true, $hub->reveal());

        $hub
            ->getRestaurants()
            ->willReturn([
                $restaurant1,
                $restaurant2,
                $restaurant3,
                $restaurant4
            ]);

        $vendor = Vendor::withHub($hub->reveal());

        $product1 = $this->prophesize(ProductInterface::class);
        $product1->getRestaurant()->willReturn($restaurant1);

        $product2 = $this->prophesize(ProductInterface::class);
        $product2->getRestaurant()->willReturn($restaurant2);

        $product3 = $this->prophesize(ProductInterface::class);
        $product3->getRestaurant()->willReturn($restaurant3);

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
            ->isMultiVendor()
            ->willReturn(true);
        $order
            ->getVendor()
            ->willReturn($vendor);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([
                $restaurant1,
                $restaurant2,
                $restaurant3
            ]));
        $order
            ->getVendors()
            ->willReturn(new ArrayCollection([
                new OrderVendor($order->reveal(), $restaurant1, 1000, 0),
                new OrderVendor($order->reveal(), $restaurant2, 1000, 0),
                new OrderVendor($order->reveal(), $restaurant3, 1000, 0),
            ]));
        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
                $this->createOrderItem($product3->reveal()),
            ]));

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

        $expectations = new \SplObjectStorage();
        $expectations[$restaurant1] = 2673;
        $expectations[$restaurant2] = 2673;
        $expectations[$restaurant3] = 2673;

        $order
            ->addRestaurant(
                Argument::type(LocalBusiness::class),
                Argument::type('int'),
                Argument::type('int')
            )
            ->should(function ($calls) use ($expectations) {
                foreach ($calls as $call) {
                    [ $restaurant, $itemsTotal, $transferAmount ] = $call->getArguments();

                    $expectedTransferAmount = $expectations[$restaurant];

                    if ($expectedTransferAmount !== $transferAmount) {
                        return false;
                    }
                }

                return true;
            });

        $order->setVendor(Argument::that(function (Vendor $vnd) use ($vendor) {
            return $vendor === $vnd;
        }))->shouldBeCalled();

        $this->orderProcessor->process($order->reveal());
    }
}
