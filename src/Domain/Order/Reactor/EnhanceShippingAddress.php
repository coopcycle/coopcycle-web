<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class EnhanceShippingAddress
{
    public function __construct(PhoneNumberUtil $phoneNumberUtil)
    {
        $this->phoneNumberUtil = $phoneNumberUtil;
    }

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
            $shippingAddress->setContactName($customer->getFullName());
        }

        if (empty($telephone) && !empty($customer->getPhoneNumber())) {
            try {
                $shippingAddress->setTelephone(
                    $this->phoneNumberUtil->parse($customer->getPhoneNumber())
                );
            } catch (NumberParseException $e) {}
        }
    }
}
