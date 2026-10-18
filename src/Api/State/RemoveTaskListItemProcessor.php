<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Tour;
use AppBundle\Exception\ItemBelongsToTourException;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Takes one named item off a task list.
 *
 * Removal used to be expressed by leaving the item out of a whole-list PUT,
 * which is byte-for-byte what a client that never heard of the item sends.
 * Naming it makes the two distinguishable.
 *
 * The call is scoped to this list and does nothing when the item is not on it,
 * so a dispatcher working from a stale board cannot unassign a task that has
 * since moved to somebody else.
 *
 * Deleting a sub-resource rather than the resource itself, so this replaces
 * API Platform's own delete processor: the task list stays, one of its items
 * goes.
 *
 * @see \AppBundle\Api\State\RemoveTaskListItemsProcessor for the batch form,
 *      which is what the dashboard uses
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class RemoveTaskListItemProcessor implements ProcessorInterface
{
    private const TYPES = [
        'tasks' => Task::class,
        'tours' => Tour::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskListManager $taskListManager,
        private RequestStack $requestStack
    ) {}

    /**
     * @param TaskList $data the list the item is being taken off
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $request = $this->requestStack->getCurrentRequest();

        // Only `id` identifies the task list, so the rest of the template is
        // read from the request rather than from $uriVariables.
        $type = (string) ($request?->attributes->get('type') ?? '');
        $class = self::TYPES[$type] ?? null;

        if (null === $class) {
            throw new NotFoundHttpException('A task list item is a task or a tour');
        }

        $taskOrTour = $this->entityManager->getRepository($class)
            ->find($request?->attributes->get('itemId'));

        if (null === $taskOrTour) {
            throw new NotFoundHttpException(sprintf('No such %s', rtrim($type, 's')));
        }

        try {
            $this->taskListManager->withLockedList(
                $data->getId(),
                fn (TaskList $list) => $this->taskListManager->removeItem($list, $taskOrTour)
            );
        } catch (ItemBelongsToTourException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return null;
    }
}
