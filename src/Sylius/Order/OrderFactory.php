<?php

namespace AppBundle\Sylius\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Customer\CustomerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class OrderFactory implements FactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly ChannelContextInterface $channelContext,
    ) {}

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

    public function createForDelivery(Delivery $delivery, ?CustomerInterface $customer = null, $attach = true): OrderInterface
    {
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

        return $order;
    }
}
