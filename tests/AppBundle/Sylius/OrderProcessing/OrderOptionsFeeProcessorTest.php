<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\OrderProcessing\OrderFeeProcessor;
use AppBundle\Sylius\OrderProcessing\OrderOptionsFeeProcessor;
use AppBundle\Sylius\OrderProcessing\OrderOptionsProcessor;
use AppBundle\Sylius\OrderProcessing\OrderVendorProcessor;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Processor\CompositeOrderProcessor;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderOptionsFeeProcessorTest extends KernelTestCase
{
    use ProphecyTrait;

    private $adjustmentFactory;
    private $orderItemQuantityModifier;

    private $orderFeeProcessor;
    private $orderOptionsProcessor;
    private $compositeProcessor;
    private $optionsFeeProcessor;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->translator
            ->trans(Argument::type('string'))
            ->willReturn('Foo');

        $this->adjustmentFactory =
            static::$kernel->getContainer()->get('sylius.factory.adjustment');

        $this->orderItemQuantityModifier =
            static::$kernel->getContainer()->get('sylius.order_item_quantity_modifier');

        $this->deliveryManager = $this->prophesize(DeliveryManager::class);

        $this->promotionRepository = $this->prophesize(PromotionRepositoryInterface::class);

        $this->orderFeeProcessor = new OrderFeeProcessor(
            $this->adjustmentFactory,
            $this->translator->reveal(),
            $this->deliveryManager->reveal(),
            $this->promotionRepository->reveal(),
            new NullLogger()
        );
        $this->orderOptionsProcessor = new OrderOptionsProcessor($this->adjustmentFactory);

        $this->orderVendorProcessor = $this->prophesize(OrderVendorProcessor::class);
        // $this->orderVendorProcessor->process(Argument::type(OrderInterface::class))->shouldBeCalled();

        $this->compositeProcessor = new CompositeOrderProcessor();

        $this->optionsFeeProcessor = new OrderOptionsFeeProcessor(
            $this->orderOptionsProcessor,
            $this->orderFeeProcessor,
            $this->orderVendorProcessor->reveal()
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

    private function createOrderItem($unitPrice)
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

    private function createProductOption($strategy)
    {
        $option = $this->prophesize(ProductOptionInterface::class);
        $option
            ->getStrategy()
            ->willReturn($strategy);

        return $option->reveal();
    }

    private function createProductOptionValue(ProductOptionInterface $option, $value, $price = null)
    {
        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue
            ->getOption()
            ->willReturn($option);
        $optionValue
            ->getValue()
            ->willReturn($value);
        $optionValue
            ->getPrice()
            ->willReturn($price);

        return $optionValue->reveal();
    }

    private function createProductVariant(array $optionValues = [])
    {
        $productVariant = $this->prophesize(ProductVariantInterface::class);
        $productVariant
            ->getOptionValues()
            ->willReturn(new ArrayCollection($optionValues));
        $productVariant
            ->getQuantityForOptionValue(Argument::type(ProductOptionValueInterface::class))
            ->willReturn(1);

        return $productVariant->reveal();
    }

    /**
     * OrderOptionsProcessor MUST be invoked BEFORE OrderFeeProcessor.
     * If we calculate
     */
    public function testFeeProcessorBeforeOptionsProcessor()
    {
        $this->compositeProcessor->addProcessor($this->orderFeeProcessor, 64);
        $this->compositeProcessor->addProcessor($this->orderOptionsProcessor, 48);

        $drinks = $this->createProductOption(ProductOptionInterface::STRATEGY_OPTION_VALUE);
        $coffee = $this->createProductOptionValue($drinks, 'Coffee', 250);
        $tea = $this->createProductOptionValue($drinks, 'Tea', 250);

        $cookieVariant = $this->createProductVariant([ $coffee ]);

        $cookie = new OrderItem();
        $cookie->setVariant($cookieVariant);
        $cookie->setUnitPrice(1000);

        $this->orderItemQuantityModifier->modify($cookie, 1);

        $cheeseCakeVariant = $this->createProductVariant();

        $cheeseCake = new OrderItem();
        $cheeseCake->setVariant($cheeseCakeVariant);
        $cheeseCake->setUnitPrice(1000);

        $this->orderItemQuantityModifier->modify($cheeseCake, 1);

        $contract = self::createContract(350, 350, 0.25);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $order->addItem($cheeseCake);
        $this->compositeProcessor->process($order);

        $order->addItem($cookie);
        $this->compositeProcessor->process($order);

        $expectedFeeTotal = (int) (((1000 + 250 + 1000) * 0.25) + 350);

        $this->assertNotEquals($expectedFeeTotal, $order->getFeeTotal());
    }

    public function testOptionsProcessorBeforeFeeProcessor()
    {
        $this->compositeProcessor->addProcessor($this->orderFeeProcessor, 48);
        $this->compositeProcessor->addProcessor($this->orderOptionsProcessor, 64);

        $drinks = $this->createProductOption(ProductOptionInterface::STRATEGY_OPTION_VALUE);
        $coffee = $this->createProductOptionValue($drinks, 'Coffee', 250);
        $tea = $this->createProductOptionValue($drinks, 'Tea', 250);

        $cookieVariant = $this->createProductVariant([ $coffee ]);

        $cookie = new OrderItem();
        $cookie->setVariant($cookieVariant);
        $cookie->setUnitPrice(1000);

        $this->orderItemQuantityModifier->modify($cookie, 1);

        $cheeseCakeVariant = $this->createProductVariant();

        $cheeseCake = new OrderItem();
        $cheeseCake->setVariant($cheeseCakeVariant);
        $cheeseCake->setUnitPrice(1000);

        $this->orderItemQuantityModifier->modify($cheeseCake, 1);

        $contract = self::createContract(350, 350, 0.25);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $order->addItem($cheeseCake);
        $this->compositeProcessor->process($order);

        $order->addItem($cookie);
        $this->compositeProcessor->process($order);

        $expectedFeeTotal = (int) (((1000 + 250 + 1000) * 0.25) + 350);

        $this->assertEquals($expectedFeeTotal, $order->getFeeTotal());
    }

    public function testCompositeProcessor()
    {
        $this->compositeProcessor->addProcessor($this->orderFeeProcessor, 48);
        $this->compositeProcessor->addProcessor($this->orderOptionsProcessor, 64);

        $drinks = $this->createProductOption(ProductOptionInterface::STRATEGY_OPTION_VALUE);
        $coffee = $this->createProductOptionValue($drinks, 'Coffee', 250);
        $tea = $this->createProductOptionValue($drinks, 'Tea', 250);

        $cookieVariant = $this->createProductVariant([ $coffee ]);

        $cookie = new OrderItem();
        $cookie->setVariant($cookieVariant);
        $cookie->setUnitPrice(1000);

        $this->orderItemQuantityModifier->modify($cookie, 1);

        $cheeseCakeVariant = $this->createProductVariant();

        $cheeseCake = new OrderItem();
        $cheeseCake->setVariant($cheeseCakeVariant);
        $cheeseCake->setUnitPrice(1000);

        $this->orderItemQuantityModifier->modify($cheeseCake, 1);

        $contract = self::createContract(350, 350, 0.25);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $order->addItem($cheeseCake);
        $this->optionsFeeProcessor->process($order);

        $order->addItem($cookie);
        $this->optionsFeeProcessor->process($order);

        $expectedFeeTotal = (int) (((1000 + 250 + 1000) * 0.25) + 350);

        $this->assertEquals($expectedFeeTotal, $order->getFeeTotal());
    }
}
