<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\SocketIoManager;

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

            if (null !== $customer) {
                $this->socketIoManager->toUserAndAdmins($customer, $event);
            } else {
                $this->socketIoManager->toAdmins($event);
            }

            if (!$order->isFoodtech()) {
                return;
            }

            $restaurant = $order->getRestaurant();
            $owners = $restaurant->getOwners();

            if (count($owners) === 0) {
                return;
            }

            foreach ($owners as $owner) {
                $this->socketIoManager->toUser($owner, $event);
            }

        } catch (\Exception $e) {

        }
    }
}
