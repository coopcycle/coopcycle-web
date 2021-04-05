<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event;
use AppBundle\Service\EmailManager;

class SendEmail
{
    private $emailManager;

    public function __construct(
        EmailManager $emailManager)
    {
        $this->emailManager = $emailManager;
    }

    public function __invoke(Event $event)
    {
        $task = $event->getTask();

        $delivery = $task->getDelivery();

        if (null === $delivery) {
            return;
        }

        $order = $delivery->getOrder();

        // Skip if this is related to foodtech
        if (null !== $order && $order->hasVendor()) {
            return;
        }

        $store = $delivery->getStore();

        if (null === $store) {
            return;
        }

        if ($event instanceof Event\TaskDone || $event instanceof Event\TaskFailed) {

            // Send email to store owners
            $owners = $store->getOwners()->toArray();
            if (count($owners) > 0) {

                $ownerMails = [];
                foreach ($owners as $owner) {
                    $ownerMails[] = sprintf('%s <%s>', $owner->getFullName(), $owner->getEmail());
                }

                $this->emailManager->sendTo(
                    $this->emailManager->createTaskCompletedMessage($task),
                    ...$ownerMails
                );
            }
        }
    }
}
