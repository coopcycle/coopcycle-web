<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Sylius\Customer\CustomerInterface;
use Webmozart\Assert\Assert;

class AddCustomerAddress
{
    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (!$order->isFoodtech()) {
            return;
        }

        if ($order->isTakeaway()) {
            return;
        }

        $customer = $order->getCustomer();
        $shippingAddress = $order->getShippingAddress();

        Assert::isInstanceOf($customer, CustomerInterface::class);

        $customer->addAddress($shippingAddress);
    }
}
