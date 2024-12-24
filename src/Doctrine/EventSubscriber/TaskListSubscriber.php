<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\TaskList;
use AppBundle\Domain\Task\Event\TaskListUpdated;
use AppBundle\Domain\Task\Event\TaskListUpdatedv2;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\TaskListRepository;
use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Nucleos\UserBundle\Model\UserInterface;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskListSubscriber implements EventSubscriber
{
    private $taskLists = [];

    public function __construct(
        private readonly MessageBus $eventBus,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslatorInterface $translator,
        private readonly RoutingInterface $routing,
        private readonly TaskListRepository $taskListRepository,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
            Events::onFlush,
            Events::postFlush,
        );
    }

    private function calculate(TaskList $taskList, EntityManagerInterface $em)
    {
        $coordinates = [];
        $tasks = [];
        $vehicle = $taskList->getVehicle();
        $uow = $em->getUnitOfWork();
        
        if (!is_null($vehicle)) {
            $coordinates[] = $taskList->getVehicle()->getWarehouse()->getAddress()->getGeo();
        }

        foreach ($taskList->getTasks() as $task) {
            $tasks[] = $task;
            $coordinates[] = $task->getAddress()->getGeo();
        }

        // going back to the warehouse
        if (!is_null($vehicle)) {
            $coordinates[] = $taskList->getVehicle()->getWarehouse()->getAddress()->getGeo();
        }

        if (count($coordinates) <= 1) {
            $taskList->setDistance(0);
            $taskList->setDuration(0);
            $taskList->setPolyline('');
        } else {
            $taskList->setDistance($this->routing->getDistance(...$coordinates));
            $taskList->setDuration($this->routing->getDuration(...$coordinates));
            $taskList->setPolyline($this->routing->getPolyline(...$coordinates));

            if (!is_null($vehicle)) {
                $route = $this->routing->route(...$coordinates)['routes'][0];
                $legs = array_slice($route["legs"], 0, -1);
                foreach ($legs as $index => $leg) {
                    $task = $taskList->getTasks()[$index];
                    $emissions = intval($vehicle->getCo2emissions() * $leg['distance'] / 1000);
                    $task->setDistanceFromPrevious(intval($leg['distance'])); // in meter
                    $task->setCo2Emissions($emissions);
                    if ($uow->isInIdentityMap($task)) {
                        $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Task::class), $task);
                    }
                }
            } else {
                $route = $this->routing->route(...$coordinates)['routes'][0];
                $legs = $route["legs"];
                foreach ($legs as $index => $leg) {
                    $task = $taskList->getTasks()[$index + 1]; // we assume we start at the first task, as there is no warehouse
                    $task->setDistanceFromPrevious(intval($leg['distance'])); // in meter
                    $task->setCo2Emissions(0); // reset
                    if ($uow->isInIdentityMap($task)) {
                        $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Task::class), $task);
                    }
                }
            }
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        if ($entity instanceof TaskList) {
            $this->calculate($entity, $em);
        }
    }

    public function addTaskList(TaskList $taskList) {
        // WARNING
        // Do not use in_array() or array_search()
        // It causes error "Nesting level too deep - recursive dependency?"
        $oid = spl_object_hash($taskList);
        if (!isset($this->taskLists[$oid])) {
            $this->taskLists[$oid] = $taskList;
        }
    }

    /**
     * Performs TaskCollection calculations when items have been modified.
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $this->taskLists = []; // reset properly the taskLists at each call, the listener may keep the array content between requests

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        $taskListItems = array_filter($entities, function ($entity) {
            return $entity instanceof Item;
        });

        foreach ($taskListItems as $taskListItem) {

            $taskList = $taskListItem->getParent();

            // When a taskListItem has been removed, its parent is NULL.
            if (!$taskList) {
                $entityChangeSet = $uow->getEntityChangeSet($taskListItem);
                [ $oldValue, $newValue ] = $entityChangeSet['parent'];
                $taskList = $oldValue;
            }

            $this->addTaskList($taskList);
        }

        $taskListsInChangeSet = array_filter($entities, function ($entity) {
            return $entity instanceof TaskList;
        });

        foreach ($taskListsInChangeSet as $taskList) {
            $entityChangeSet = $uow->getEntityChangeSet($taskList);

            if(!isset($entityChangeSet['vehicle'])) {
                continue;
            }

            [ $oldValue, $newValue ] = $entityChangeSet['vehicle']; // recalculate distances and co2 when starting vehicle/warehouse has changed 

            if ($oldValue !== $newValue) {
                $this->addTaskList($taskList);
            }
        }

        foreach ($this->taskLists as $taskList) { // @phpstan-ignore-line

            $this->logger->debug('TaskList was modified, recalculating…');
            $this->calculate($taskList, $em);

            if ($uow->isInIdentityMap($taskList)) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(TaskList::class), $taskList);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $this->logger->debug(sprintf('TaskLists updated = %d', count($this->taskLists)));

        if (count($this->taskLists) === 0) {
            return;
        }

        $usersByDate = new \SplObjectStorage();
        foreach ($this->taskLists as $taskList) {

            // legacy event and new version of event
            // see https://github.com/coopcycle/coopcycle-app/issues/1803
            $myTaskListDto = $this->taskListRepository->findMyTaskListAsDto($taskList->getCourier(), $taskList->getDate());
            $this->eventBus->handle(new TaskListUpdated($taskList->getCourier(), $myTaskListDto));
            $this->eventBus->handle(new TaskListUpdatedv2($taskList));

            $date = $taskList->getDate();
            $users = isset($usersByDate[$date]) ? $usersByDate[$date] : [];

            $usersByDate[$date] = array_merge($users, [
                $taskList->getCourier()
            ]);
        }

        if (count($usersByDate) === 0) {
            return;
        }

        $now = Carbon::now();

        foreach ($usersByDate as $date) {

            $users = $usersByDate[$date];
            $users = array_unique($users);

            // We do not send push notifications to users with role ROLE_ADMIN,
            // they have WebSockets to get live updates
            $users = array_filter($users, fn(UserInterface $user) => !$user->hasRole('ROLE_ADMIN'));

            if (count($users) === 0) {
                continue;
            }

            $usernames = array_map(fn(UserInterface $user) => $user->getUsername(), $users);

            $data = [
                'event' => [
                    'name' => 'tasks:changed',
                    'data' => [
                        'date' => $date->format('Y-m-d')
                    ]
                ]
            ];

            if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
                $message = $this->translator->trans('notifications.tasks_changed_today');
            } else {
                $message = $this->translator->trans('notifications.tasks_changed', [
                    '%date%' => $date->format('Y-m-d'),
                ]);
            }

            if (RemotePushNotificationManager::isEnabled()) {

                $this->logger->debug(sprintf('Sending push notification to %s', implode(', ', $usernames)));

                $this->messageBus->dispatch(
                    new PushNotification($message, $usernames, $data)
                );
            }
        }
    }
}
