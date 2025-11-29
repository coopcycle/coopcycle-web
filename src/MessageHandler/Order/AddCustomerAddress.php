<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Sylius\Customer\CustomerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler()]
class AddCustomerAddress
{
    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (!$order->hasVendor()) {
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
