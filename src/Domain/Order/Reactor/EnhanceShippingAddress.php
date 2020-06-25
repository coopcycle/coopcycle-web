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

        if ($order->isTakeaway()) {
            return;
        }

        $customer = $order->getCustomer();
        $shippingAddress = $order->getShippingAddress();

        $contactName = $shippingAddress->getContactName();
        $telephone = $shippingAddress->getTelephone();

        if (empty($contactName)) {
            $shippingAddress->setContactName(
                trim(sprintf('%s %s', $customer->getGivenName(), $customer->getFamilyName()))
            );
        }

        if (empty($telephone)) {
            $shippingAddress->setTelephone($customer->getTelephone());
        }
    }
}
