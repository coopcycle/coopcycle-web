<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event\TaskCancelled;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\PublishLiveUpdate as PublishLiveUpdateMessage;
use AppBundle\Service\LiveUpdates;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
class PublishLiveUpdateHandler
{
    /**
     * The status a task must already be in for the event to be worth publishing.
     * Events whose outcome is not a status (assignment, barcode scans, ...) are
     * absent and are published without the check.
     */
    private const SETTLED_STATUS = [
        TaskDone::class => Task::STATUS_DONE,
        TaskFailed::class => Task::STATUS_FAILED,
        TaskStarted::class => Task::STATUS_DOING,
        TaskCancelled::class => Task::STATUS_CANCELLED,
    ];

    private array $roles = LiveUpdates::DISPATCH_ROLES;

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

        $this->assertTaskReflectsEvent($task, $eventClass);

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

    /**
     * This handler is given a task id, not a task, so it re-reads the row. The
     * message is queued from the domain event, which is dispatched before the
     * request has even flushed -- so the read can land on a version of the task
     * that predates the change the event is about.
     *
     * Publishing it anyway sends a `task:done` carrying `status=TODO`, and the
     * dispatch board renders a task as still to do, minutes after the courier
     * completed it. Observed on lcr: task#141178 published `task:done` with
     * `status=TODO` and an `updatedAt` 22 minutes behind.
     *
     * Hand the message back to Messenger instead. The async transport retries
     * after 1s, 2s and 4s, by which point the request has committed.
     */
    private function assertTaskReflectsEvent(Task $task, string $eventClass): void
    {
        $settled = self::SETTLED_STATUS[$eventClass] ?? null;

        if (null === $settled || $task->getStatus() === $settled) {
            return;
        }

        // A previous attempt may have left an identity-mapped copy behind, so
        // make sure the mismatch is not just a cached read before giving up.
        $this->entityManager->refresh($task);

        if ($task->getStatus() === $settled) {
            return;
        }

        throw new RecoverableMessageHandlingException(sprintf(
            'Task #%d is still %s, the %s it was published for has not been committed yet',
            $task->getId(),
            $task->getStatus(),
            $eventClass
        ));
    }
}
