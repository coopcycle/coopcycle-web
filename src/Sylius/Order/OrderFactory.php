<?php

namespace AppBundle\Sylius\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Product\ProductVariantFactory;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Webmozart\Assert\Assert;

class OrderFactory implements FactoryInterface
{
    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var ChannelContextInterface
     */
    private $channelContext;

    /**
     * @var FactoryInterface $orderItemFactory
     */
    private $orderItemFactory;

    /**
     * @var ProductVariantFactoryInterface $productVariantFactory
     */
    private $productVariantFactory;

    /**
     * @var OrderItemQuantityModifierInterface $orderItemQuantityModifier
     */
    private $orderItemQuantityModifier;

    /**
     * @var OrderModifierInterface $orderModifier
     */
    private $orderModifier;

    /**
     * @param FactoryInterface $factory
     */
    public function __construct(
        FactoryInterface $factory,
        ChannelContextInterface $channelContext,
        FactoryInterface $orderItemFactory,
        ProductVariantFactoryInterface $productVariantFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier)
    {
        $this->factory = $factory;
        $this->channelContext = $channelContext;
        $this->orderItemFactory = $orderItemFactory;
        $this->productVariantFactory = $productVariantFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew()
    {
        $order = $this->factory->createNew();
        $order->setChannel($this->channelContext->getChannel());

        return $order;
    }

    public function createForRestaurant(LocalBusiness $restaurant)
    {
        $order = $this->createNew();
        $order->addRestaurant($restaurant);

        if (!$restaurant->isFulfillmentMethodEnabled('delivery') && $restaurant->isFulfillmentMethodEnabled('collection')) {
            $order->setTakeaway(true);
        }

        return $order;
    }

    public function createForDelivery(Delivery $delivery, int $price, ?CustomerInterface $customer = null, $attach = true)
    {
        Assert::isInstanceOf($this->productVariantFactory, ProductVariantFactory::class);

        $order = $this->factory->createNew();

        if ($attach) {
            $order->setDelivery($delivery);
        }

        $order->setShippingAddress($delivery->getDropoff()->getAddress());

        $shippingTimeRange = new TsRange();

        if (null === $delivery->getDropoff()->getAfter()) {
            $dropoffAfter = clone $delivery->getDropoff()->getBefore();
            $dropoffAfter->modify('-15 minutes');
            $delivery->getDropoff()->setAfter($dropoffAfter);
        }
        $shippingTimeRange->setLower($delivery->getDropoff()->getAfter());
        $shippingTimeRange->setUpper($delivery->getDropoff()->getBefore());

        $order->setShippingTimeRange($shippingTimeRange);

        if (null !== $customer) {
            $order->setCustomer($customer);
        }

        $variant = $this->productVariantFactory->createForDelivery($delivery, $price);

        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice($variant->getPrice());

        $this->orderItemQuantityModifier->modify($orderItem, 1);

        $this->orderModifier->addToOrder($order, $orderItem);

        return $order;
    }
}
