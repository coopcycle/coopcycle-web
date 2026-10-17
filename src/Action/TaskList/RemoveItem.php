<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Tour;
use AppBundle\Exception\ItemBelongsToTourException;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
final class RemoveItem
{
    private const TYPES = [
        'tasks' => Task::class,
        'tours' => Tour::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskListManager $taskListManager
    ) {}

    public function __invoke(Request $request): Response
    {
        $taskList = $this->entityManager->getRepository(TaskList::class)
            ->find($request->get('id'));

        if (null === $taskList) {
            throw new NotFoundHttpException('Task list not found');
        }

        $type = (string) $request->get('type');
        $class = self::TYPES[$type] ?? null;

        if (null === $class) {
            throw new NotFoundHttpException('A task list item is a task or a tour');
        }

        $taskOrTour = $this->entityManager->getRepository($class)
            ->find($request->get('itemId'));

        if (null === $taskOrTour) {
            throw new NotFoundHttpException(sprintf('No such %s', rtrim($type, 's')));
        }

        try {
            $this->taskListManager->removeItem($taskList, $taskOrTour);
        } catch (ItemBelongsToTourException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
