<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;

class SendEmail
{
    private $emailManager;
    private $settingsManager;

    public function __construct(
        EmailManager $emailManager,
        SettingsManager $settingsManager)
    {
        $this->emailManager = $emailManager;
        $this->settingsManager = $settingsManager;
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
        if (null !== $order && $order->isFoodtech()) {
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
                    $ownerMails[$owner->getEmail()] = $owner->getFullName();
                }

                $this->emailManager->sendTo(
                    $this->emailManager->createTaskCompletedMessage($task),
                    $ownerMails
                );
            }
        }
    }
}
