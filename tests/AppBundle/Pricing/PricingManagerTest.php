<?php

declare(strict_types=1);

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use AppBundle\Pricing\PriceCalculationVisitor;
use AppBundle\Pricing\PriceReviewIncidents;
use AppBundle\Pricing\PriceUpdateVisitor;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\TimeSlotManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An order item is what holds the price of an on demand delivery order,
 * so an order left without any of them can not even be displayed.
 *
 * @see PricingManager::processDeliveryOrder()
 */
class PricingManagerTest extends TestCase
{
    use ProphecyTrait;

    private $orderItemFactory;
    private $orderItemQuantityModifier;
    private $orderModifier;
    private $productVariantFactory;
    private $orderProcessor;
    private $priceReviewIncidents;

    private PricingManager $pricingManager;

    public function setUp(): void
    {
        $this->orderItemFactory = $this->prophesize(FactoryInterface::class);
        $this->orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $this->orderModifier = $this->prophesize(OrderModifierInterface::class);
        $this->productVariantFactory = $this->prophesize(ProductVariantFactory::class);
        $this->orderProcessor = $this->prophesize(OrderProcessorInterface::class);
        $this->priceReviewIncidents = $this->prophesize(PriceReviewIncidents::class);

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('form.delivery.price.missing')->willReturn('Price to be reviewed');

        $this->pricingManager = new PricingManager(
            $this->prophesize(EntityManagerInterface::class)->reveal(),
            $this->prophesize(NormalizerInterface::class)->reveal(),
            $translator->reveal(),
            $this->orderItemFactory->reveal(),
            $this->orderItemQuantityModifier->reveal(),
            $this->orderModifier->reveal(),
            $this->productVariantFactory->reveal(),
            $this->orderProcessor->reveal(),
            $this->prophesize(TimeSlotManager::class)->reveal(),
            $this->prophesize(PriceCalculationVisitor::class)->reveal(),
            $this->prophesize(PriceUpdateVisitor::class)->reveal(),
            $this->priceReviewIncidents->reveal(),
            new NullLogger()
        );
    }

    private function createOrder(?Delivery $delivery, array $items): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->isFoodtech()->willReturn(false);
        $order->getDelivery()->willReturn($delivery);
        $order->getId()->willReturn(1);
        $order->getItems()->willReturn(new ArrayCollection($items));

        return $order->reveal();
    }

    private function expectOrderItemCreated(): OrderItemInterface
    {
        $orderItem = $this->prophesize(OrderItemInterface::class);
        $orderItem->setVariant(Argument::type(ProductVariantInterface::class))->shouldBeCalled();
        $orderItem->setUnitPrice(Argument::any())->shouldBeCalled();
        $orderItem->setImmutable(true)->shouldBeCalled();

        $this->orderItemFactory->createNew()->willReturn($orderItem->reveal());

        return $orderItem->reveal();
    }

    public function testProcessDeliveryOrderWithoutAnyPriceSetsAPlaceholderPrice()
    {
        $delivery = Delivery::createWithTasks(new Task(), new Task());

        $previousItem = $this->prophesize(OrderItemInterface::class)->reveal();
        $order = $this->createOrder($delivery, [$previousItem]);

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getPrice()->willReturn(0);

        $this->productVariantFactory
            ->createWithPrice($delivery, Argument::type(ArbitraryPrice::class))
            ->willReturn($variant->reveal())
            ->shouldBeCalled();

        $orderItem = $this->expectOrderItemCreated();

        $this->orderModifier->removeFromOrder($order, $previousItem)->shouldBeCalled();
        $this->orderModifier->addToOrder($order, $orderItem)->shouldBeCalled();
        $this->orderItemQuantityModifier->modify($orderItem, 1)->shouldBeCalled();
        $this->orderProcessor->process($order)->shouldBeCalled();

        $this->priceReviewIncidents->schedule($order)->shouldBeCalled();

        $this->pricingManager->processDeliveryOrder($order, []);
    }

    public function testProcessDeliveryOrderWithoutAnyPriceKeepsTheItemsOfAnOrderWithoutADelivery()
    {
        $previousItem = $this->prophesize(OrderItemInterface::class)->reveal();
        $order = $this->createOrder(null, [$previousItem]);

        $this->orderModifier->removeFromOrder(Argument::cetera())->shouldNotBeCalled();
        $this->orderProcessor->process(Argument::any())->shouldNotBeCalled();
        $this->priceReviewIncidents->schedule(Argument::any())->shouldNotBeCalled();

        $this->pricingManager->processDeliveryOrder($order, []);
    }

    public function testProcessDeliveryOrderWithAPriceDoesNotCreateAnIncident()
    {
        $delivery = Delivery::createWithTasks(new Task(), new Task());

        $order = $this->createOrder($delivery, []);

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getPrice()->willReturn(1000);

        $orderItem = $this->expectOrderItemCreated();

        $this->orderModifier->addToOrder($order, $orderItem)->shouldBeCalled();
        $this->orderItemQuantityModifier->modify($orderItem, 1)->shouldBeCalled();
        $this->orderProcessor->process($order)->shouldBeCalled();

        $this->priceReviewIncidents->schedule(Argument::any())->shouldNotBeCalled();

        $this->pricingManager->processDeliveryOrder($order, [$variant->reveal()]);
    }
}
