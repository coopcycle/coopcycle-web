<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\LiveUpdates;
use AppBundle\Sylius\Customer\CustomerInterface;
use Webmozart\Assert\Assert;

class PublishLiveUpdate
{
    private $liveUpdates;

    public function __construct(LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();
        $customer = $order->getCustomer();

        Assert::isInstanceOf($customer, CustomerInterface::class);

        $this->liveUpdates->toOrderWatchers($order, $event);

        if (null !== $customer && $customer->hasUser()) {
            $this->liveUpdates->toUserAndAdmins($customer->getUser(), $event);
        } else {
            $this->liveUpdates->toAdmins($event);
        }

        // No need to continue if the order has no vendor
        if (!$order->hasVendor()) {
            return;
        }

        // When this is a multi vendor order,
        // we do *NOT* send a live update when the order is created
        if ($event instanceof Event\OrderCreated && $order->isMultiVendor()) {
            return;
        }

        $owners = $order->getVendor()->getOwners();

        if (count($owners) === 0) {
            return;
        }

        $this->liveUpdates->toUsers($owners->toArray(), $event);
    }
}
