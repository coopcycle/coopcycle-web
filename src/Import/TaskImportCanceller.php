<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskRepository;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Cancels the tasks a file import created. Those already under way are left as is.
 *
 * Simpler than its delivery counterpart: an imported task stands on its own, with no
 * order to cancel with it.
 *
 * The task groups are deliberately left in place, empty: they are the trail of what the
 * file brought in, which is what a dispatcher looks at to see what was cancelled.
 */
class TaskImportCanceller implements ImportCancellerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskManager $taskManager,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function getType(): string
    {
        return ImportQueue::TYPE_TASKS;
    }

    public function findIds(ImportQueue $queue): array
    {
        /** @var TaskRepository $repository */
        $repository = $this->entityManager->getRepository(Task::class);

        return $repository->findIdsByImport($queue->getId(), $queue->getOrganization()?->getId());
    }

    public function cancel(int $id, ImportQueue $queue): bool
    {
        /** @var ?Task $task */
        $task = $this->entityManager->find(Task::class, $id);

        if (is_null($task)) {
            return false;
        }

        $context = ['import_id' => $queue->getId(), 'import_type' => $queue->getType()];

        // Cancelled since by a dispatcher, or by an earlier attempt at this
        if ($task->isCancelled()) {
            return true;
        }

        // Assigned tasks are cancelled too, as from the dispatch board: only those
        // started, done or failed are left alone
        if (Task::STATUS_TODO !== $task->getStatus()) {
            $this->logger->info(
                sprintf('Leaving task #%d as is, it is %s', $task->getId(), $task->getStatus()),
                $context
            );

            return false;
        }

        try {
            $this->taskManager->cancelTasks([$task]);
        } catch (HandlerFailedException $e) {
            // Not to be flushed with the next tasks
            $this->entityManager->refresh($task);

            $this->logger->warning(
                sprintf('Could not cancel task #%d: %s', $task->getId(), $e->getMessage()),
                $context
            );

            return false;
        }

        return true;
    }
}
