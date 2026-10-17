<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Entity\User;
use AppBundle\Service\TaskListManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated Replaces the whole task list, so a task the payload leaves out
 *   is unassigned — and a client that has simply not heard of a task sends
 *   exactly what one deliberately removing it sends. Replaced by
 *   `POST /api/task_lists/{id}/items`, `DELETE /api/task_lists/{id}/items/...`
 *   and `PUT /api/task_lists/{id}/order`.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
final class SetItems
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserManager $userManager,
        private TaskListManager $taskListManager,
        private TaskListProvider $taskListProvider,
        private LoggerInterface $logger = new NullLogger()
    )
    {}

    public function __invoke(Request $request)
    {
        // Who is still writing whole lists. This is the measurement the sunset
        // date depends on: the endpoint can only be removed once nothing but
        // retired app versions appears here. One line per call, on the channel
        // the unassignment warnings are read on — the message is constant and
        // the client is in the context, so it collapses to a single pattern
        // and can be counted by User-Agent.
        $this->logger->info('Deprecated set_items write', [
            'user_agent' => $request->headers->get('User-Agent', 'unknown client'),
            'courier' => $request->get('username'),
        ]);

        $date = new \DateTime($request->get('date'));
        $user = $this->userManager->findUserByUsername($request->get('username'));

        // Tasks are sent as JSON payload
        $data = $request->toArray();

        return $this->entityManager->wrapInTransaction(function () use ($date, $user, $data) {

            $taskList = $this->taskListProvider->getTaskListForUserAndDate($date, $user);

            // This rewrites every item on the list, so it has to queue behind
            // the endpoints that replaced it rather than interleave with them
            // — they hold the row while they work, and a writer that did not
            // would still be able to deadlock against them.
            //
            // It does not make this endpoint safe: it still replaces the list
            // with whatever the client last saw. It only stops it taking the
            // database down with it.
            if (null !== $taskList->getId()) {
                $this->entityManager->lock($taskList, LockMode::PESSIMISTIC_WRITE);
            }

            $preloader = new EntityPreloader($this->entityManager);
            $preloader->preload($taskList->getTasks(), 'assignedTo');

            $this->taskListManager->assign($taskList, $data['items']);

            return $taskList;
        });
    }
}
