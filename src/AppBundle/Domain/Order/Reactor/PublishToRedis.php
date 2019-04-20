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

            $customer = $event->getOrder()->getCustomer();
            if (null !== $customer) {
                $this->socketIoManager->toUserAndAdmins($customer, $event);
            } else {
                $this->socketIoManager->toAdmins($event);
            }

        } catch (\Exception $e) {

        }
    }
}
