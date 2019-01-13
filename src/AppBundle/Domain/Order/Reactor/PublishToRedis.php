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
            $this->socketIoManager->toAdmins($event);

            $customer = $event->getOrder()->getCustomer();
            if (null !== $customer) {
                $this->socketIoManager->toUser($customer, $event);
            }
        } catch (\Exception $e) {

        }
    }
}
