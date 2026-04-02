<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\PublishLiveUpdate as PublishLiveUpdateMessage;
use AppBundle\Service\LiveUpdates;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PublishLiveUpdateHandler
{
    private array $roles = ['ROLE_ADMIN', 'ROLE_DISPATCHER'];

    public function __construct(
        private LiveUpdates $liveUpdates,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(PublishLiveUpdateMessage $message)
    {
        $task = $this->entityManager->getRepository(Task::class)->find($message->taskId);

        if (!$task) {
            return;
        }

        $eventClass = $message->eventClass;
        $event = $eventClass::fromTask($task);

        if (is_a($eventClass, TaskUpdated::class, true)) {
            $courier = $task->getAssignedCourier();
            if (null !== $courier) {
                $this->liveUpdates->toUserAndRoles($courier, $this->roles, $event);
                return;
            }
        }

        $this->liveUpdates->toRoles($this->roles, $event);
    }
}
