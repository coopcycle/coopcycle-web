<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Sylius\Customer\CustomerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler()]
class EnhanceShippingAddress
{
    public function __construct(private readonly PhoneNumberUtil $phoneNumberUtil)
    {
    }

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

        if (empty($customer->getPhoneNumber()) && !empty($telephone)) {

            Assert::isInstanceOf($customer, CustomerInterface::class);

            $customer->setTelephone($telephone);
        }
    }
}
