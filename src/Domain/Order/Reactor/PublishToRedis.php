<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\LiveUpdates;
use AppBundle\Sylius\Customer\CustomerInterface;
use Webmozart\Assert\Assert;

class PublishToRedis
{
    private $liveUpdates;

    public function __construct(LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }

    public function __invoke(Event $event)
    {
        try {

            $order = $event->getOrder();
            $customer = $order->getCustomer();

            Assert::isInstanceOf($customer, CustomerInterface::class);

            $this->liveUpdates->toOrderWatchers($order, $event);

            if (null !== $customer && $customer->hasUser()) {
                $this->liveUpdates->toUserAndAdmins($customer->getUser(), $event);
            } else {
                $this->liveUpdates->toAdmins($event);
            }

            if (!$order->hasVendor() || $order->getVendor()->isHub()) {
                return;
            }

            $owners = $order->getVendor()->getOwners();

            if (count($owners) === 0) {
                return;
            }

            $this->liveUpdates->toUsers($owners->toArray(), $event);

        } catch (\Exception $e) {

        }
    }
}
