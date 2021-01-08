<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\SocketIoManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use Webmozart\Assert\Assert;

class PublishToRedis
{
    private $socketIoManager;

    public function __construct(SocketIoManager $socketIoManager)
    {
        $this->socketIoManager = $socketIoManager;
    }

    public function __invoke(Event $event)
    {
        try {

            $order = $event->getOrder();
            $customer = $order->getCustomer();

            Assert::isInstanceOf($customer, CustomerInterface::class);

            $this->socketIoManager->toOrderWatchers($order, $event);

            if (null !== $customer && $customer->hasUser()) {
                $this->socketIoManager->toUserAndAdmins($customer->getUser(), $event);
            } else {
                $this->socketIoManager->toAdmins($event);
            }

            if (!$order->hasVendor() || $order->getVendor()->isHub()) {
                return;
            }

            $owners = $order->getVendor()->getOwners();

            if (count($owners) === 0) {
                return;
            }

            $this->socketIoManager->toUsers($owners->toArray(), $event);

        } catch (\Exception $e) {

        }
    }
}
