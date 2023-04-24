<?php

namespace AppBundle\Sylius\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Quote;
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
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\Persistence\ManagerRegistry;

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
        $order->setRestaurant($restaurant);

        if (!$restaurant->isFulfillmentMethodEnabled('delivery') && $restaurant->isFulfillmentMethodEnabled('collection')) {
            $order->setTakeaway(true);
        }

        return $order;
    }

    public function createForDelivery(Delivery $delivery, int $price, ?CustomerInterface $customer = null, $attach = true)
    {
        $log = new Logger('createForQuote');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('OrderFactory - createForDelivery - Test point 1');

        Assert::isInstanceOf($this->productVariantFactory, ProductVariantFactory::class);

        $log->warning('OrderFactory - createForDelivery - Test point 2');
        $order = $this->factory->createNew();

        $log->warning('OrderFactory - createForDelivery - Test point 3');
        if ($attach) {
            $order->setDelivery($delivery);
        }

        $log->warning('OrderFactory - createForDelivery - Test point 4');
        $order->setShippingAddress($delivery->getDropoff()->getAddress());

        $log->warning('OrderFactory - createForDelivery - Test point 5');
        $shippingTimeRange = new TsRange();

        $log->warning('OrderFactory - createForDelivery - Test point 6');
        if (null === $delivery->getDropoff()->getAfter()) {
            $dropoffAfter = clone $delivery->getDropoff()->getBefore();
            $dropoffAfter->modify('-15 minutes');
            $delivery->getDropoff()->setAfter($dropoffAfter);
        }
        $log->warning('OrderFactory - createForDelivery - Test point 7');
        $shippingTimeRange->setLower($delivery->getDropoff()->getAfter());
        $shippingTimeRange->setUpper($delivery->getDropoff()->getBefore());

        $log->warning('OrderFactory - createForDelivery - Test point 8');
        $order->setShippingTimeRange($shippingTimeRange);

        $log->warning('OrderFactory - createForDelivery - Test point 9');
        if (null !== $customer) {
            $order->setCustomer($customer);
        }

        $log->warning('OrderFactory - createForDelivery - Test point 10');
        $variant = $this->productVariantFactory->createForDelivery($delivery, $price);

        $log->warning('OrderFactory - createForDelivery - Test point 11');
        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice($variant->getPrice());

        $log->warning('OrderFactory - createForDelivery - Test point 12');
        $this->orderItemQuantityModifier->modify($orderItem, 1);

        $log->warning('OrderFactory - createForDelivery - Test point 13');
        $this->orderModifier->addToOrder($order, $orderItem);

        $log->warning('OrderFactory - createForDelivery - Test point 14');
        $log->warning('OrderFactory - createForDelivery - $order: ' . $order->getItemsTotalExcludingTax());
        
        return $order;
    }

    public function createForQuote(Quote $quote, int $price, ?CustomerInterface $customer = null, $attach = true)
    {
        $log = new Logger('createForQuote');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('OrderFactory - createForQuote - Test point 1');

        Assert::isInstanceOf($this->productVariantFactory, ProductVariantFactory::class);

        $order = $this->factory->createNew();

        if ($attach) {
            $order->setQuote($quote);
        }

        $order->setShippingAddress($quote->getDropoff()->getAddress());

        $shippingTimeRange = new TsRange();

        if (null === $quote->getDropoff()->getAfter()) {
            $dropoffAfter = clone $quote->getDropoff()->getBefore();
            $dropoffAfter->modify('-15 minutes');
            $quote->getDropoff()->setAfter($dropoffAfter);
        }
        $shippingTimeRange->setLower($quote->getDropoff()->getAfter());
        $shippingTimeRange->setUpper($quote->getDropoff()->getBefore());

        $order->setShippingTimeRange($shippingTimeRange);

        if (null !== $customer) {
            $order->setCustomer($customer);
        }

        $variant = $this->productVariantFactory->createForQuote($quote, $price);

        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice($variant->getPrice());

        $this->orderItemQuantityModifier->modify($orderItem, 1);

        $this->orderModifier->addToOrder($order, $orderItem);

        return $order;
    }
}
