<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\AddTaskListItemsInput;
use AppBundle\Entity\TaskList;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds items to a task list without replacing it.
 *
 * `PUT /api/task_lists/set_items/{date}/{username}` takes the whole list as
 * absolute state, so a client that has not heard about a task expresses that
 * identically to a dispatcher who deliberately removed it. This operation
 * names what it wants instead, which makes the two distinguishable and the
 * stale write harmless.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class AddTaskListItemsProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskListManager $taskListManager
    ) {}

    /**
     * @param AddTaskListItemsInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $taskList = $this->entityManager->getRepository(TaskList::class)
            ->find($uriVariables['id'] ?? null);

        if (null === $taskList) {
            throw new NotFoundHttpException('Task list not found');
        }

        $this->taskListManager->addItems($taskList, $data->items, $data->position);

        $this->entityManager->flush();

        return $taskList;
    }
}
