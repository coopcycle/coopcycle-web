<?php

namespace AppBundle\Sylius\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PriceInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Product\ProductVariantFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Webmozart\Assert\Assert;

class OrderFactory implements FactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly ChannelContextInterface $channelContext,
        private readonly FactoryInterface $orderItemFactory,
        private readonly ProductVariantFactory $productVariantFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly LoggerInterface $logger
    )
    {
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

    public function createForDeliveryAndPrice(Delivery $delivery, PriceInterface $price, ?CustomerInterface $customer = null, $attach = true): OrderInterface
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

        $this->setDeliveryPrice($order, $delivery, $price);

        return $order;
    }

    private function setDeliveryPrice(OrderInterface $order, Delivery $delivery, PriceInterface $price)
    {
        $variant = $this->productVariantFactory->createForDelivery($delivery, $price->getValue());

        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice($variant->getPrice());

        if ($price instanceof ArbitraryPrice) {
            $orderItem->setImmutable(true);
            $variant->setName($price->getVariantName());
            $variant->setCode(Uuid::uuid4()->toString());
        }

        $this->orderItemQuantityModifier->modify($orderItem, 1);

        $this->orderModifier->addToOrder($order, $orderItem);
    }

    public function updateDeliveryPrice(OrderInterface $order, Delivery $delivery, PriceInterface $price)
    {
        if ($order->isFoodtech()) {
            $this->logger->info('Price update is not supported for foodtech orders');
            return;
        }

        $deliveryItem = $order->getItems()->first();

        if (false === $deliveryItem) {
            $this->logger->info('No delivery item found in order');
        }

        // remove the previous price
        $this->orderModifier->removeFromOrder($order, $deliveryItem);

        $this->setDeliveryPrice($order, $delivery, $price);
    }
}
