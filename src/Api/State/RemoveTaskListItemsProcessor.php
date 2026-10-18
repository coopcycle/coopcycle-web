<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\RemoveTaskListItemsInput;
use AppBundle\Entity\TaskList;
use AppBundle\Exception\ItemBelongsToTourException;
use AppBundle\Service\TaskListManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Takes several named items off a task list, in one transaction.
 *
 * Clearing a list is one thing a dispatcher does, not twenty, and it has to
 * reach the database that way. Removing items one request at a time had each
 * one renumbering every surviving row, so a handful of them running at once
 * took row locks on the same table in different orders and Postgres killed
 * one with a deadlock.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class RemoveTaskListItemsProcessor implements ProcessorInterface
{
    public function __construct(
        private TaskListManager $taskListManager,
        private IriConverterInterface $iriConverter
    ) {}

    /**
     * @param RemoveTaskListItemsInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $tasksAndTours = array_map(
            fn (string $itemIri) => $this->iriConverter->getResourceFromIri($itemIri),
            $data->items
        );

        try {
            $taskList = $this->taskListManager->withLockedList(
                (int) ($uriVariables['id'] ?? 0),
                fn (TaskList $list) => $this->taskListManager->removeItems($list, $tasksAndTours)
            );
        } catch (ItemBelongsToTourException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        if (null === $taskList) {
            throw new NotFoundHttpException('Task list not found');
        }

        return $taskList;
    }
}
