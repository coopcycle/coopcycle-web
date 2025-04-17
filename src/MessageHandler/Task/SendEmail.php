<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Service\EmailManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmail
{
    private $emailManager;

    public function __construct(
        EmailManager $emailManager)
    {
        $this->emailManager = $emailManager;
    }
    public function __invoke(TaskDone|TaskFailed $event)
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
