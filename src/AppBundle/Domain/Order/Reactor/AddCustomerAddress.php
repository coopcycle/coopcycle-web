<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;

class AddCustomerAddress
{
    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (!$order->isFoodtech()) {
            return;
        }

        $customer = $order->getCustomer();
        $shippingAddress = $order->getShippingAddress();

        if (!$customer->getAddresses()->contains($shippingAddress)) {
            $customer->addAddress($shippingAddress);
        }
    }
}
