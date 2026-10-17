<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\TaskListOrderInput;
use AppBundle\Api\Precondition\IfMatch;
use AppBundle\Entity\TaskList;
use AppBundle\Exception\OrderChangesMembershipException;
use AppBundle\Service\TaskListManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Reorders a task list.
 *
 * This is the one whole-list write that survives the split, because ordering
 * is the one thing that genuinely needs to be expressed as a whole. It is made
 * safe by two things: it refuses any payload that would change which items are
 * on the list, and it requires the client to say which version it is ordering.
 *
 * Both are mandatory here, unlike on `PUT /api/tours/{id}`, where `If-Match`
 * stayed optional so the guard could ship ahead of the clients. Nothing writes
 * to this endpoint yet, so there is no fleet to wait for.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class SetTaskListOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private TaskListManager $taskListManager,
        private RequestStack $requestStack
    ) {}

    /**
     * @param TaskListOrderInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        try {
            $taskList = $this->taskListManager->withLockedList(
                (int) ($uriVariables['id'] ?? 0),
                function (TaskList $list) use ($data) {
                    // Both of these run with the row held, so the version the
                    // client is held to, and the membership it is checked
                    // against, are what the write will actually land on.
                    $this->assertNotStale($list);
                    $this->taskListManager->reorder($list, $data->items);
                }
            );
        } catch (OrderChangesMembershipException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        if (null === $taskList) {
            throw new NotFoundHttpException('Task list not found');
        }

        return $taskList;
    }

    /**
     * A task list is not a Doctrine version field — arming optimistic locking
     * on it would make every concurrent `set_items` write fail for clients
     * that cannot yet handle it — so the version is compared here rather than
     * enforced on the UPDATE.
     *
     * Called with the list's row held, which is what makes comparing it
     * enough: no other write can land between the comparison and the flush,
     * because no other write can start.
     */
    private function assertNotStale(TaskList $taskList): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $expected = null === $request ? null : IfMatch::expectedVersion($request);

        if (null === $expected) {
            throw new PreconditionRequiredHttpException(sprintf(
                'Send If-Match with the version of the task list you are ordering (currently "%d")',
                $taskList->getVersion()
            ));
        }

        if ($expected !== $taskList->getVersion()) {
            throw new ConflictHttpException(sprintf(
                'Task list %d is at version %d, not %d; refetch it and retry',
                $taskList->getId(),
                $taskList->getVersion(),
                $expected
            ));
        }
    }
}
