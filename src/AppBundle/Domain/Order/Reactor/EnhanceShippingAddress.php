<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;

class EnhanceShippingAddress
{
    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (!$order->isFoodtech()) {
            return;
        }

        $customer = $order->getCustomer();
        $shippingAddress = $order->getShippingAddress();

        $contactName = $shippingAddress->getContactName();
        $telephone = $shippingAddress->getTelephone();

        if (empty($contactName)) {
            $shippingAddress->setContactName($customer->getFullName());
        }

        if (empty($telephone)) {
            $shippingAddress->setTelephone($customer->getPhoneNumber());
        }
    }
}
