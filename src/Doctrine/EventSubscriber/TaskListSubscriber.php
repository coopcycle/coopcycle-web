<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskCollection;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskList;
use AppBundle\Domain\Task\Event\TaskListUpdated;
use AppBundle\Domain\Task\Event\TaskListUpdatedv2;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Doctrine\Common\EventSubscriber;
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
    private $eventBus;
    private $messageBus;
    private $translator;
    private $routing;
    private $logger;
    private $taskLists = [];

    public function __construct(
        MessageBus $eventBus,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
        RoutingInterface $routing,
        LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->messageBus = $messageBus;
        $this->translator = $translator;
        $this->routing = $routing;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
            Events::onFlush,
            Events::postFlush,
        );
    }

    private function calculate(TaskList $taskList)
    {
        $coordinates = [];
        foreach ($taskList->getTasks() as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        if (count($coordinates) <= 1) {
            $taskList->setDistance(0);
            $taskList->setDuration(0);
            $taskList->setPolyline('');
        } else {
            $taskList->setDistance($this->routing->getDistance(...$coordinates));
            $taskList->setDuration($this->routing->getDuration(...$coordinates));
            $taskList->setPolyline($this->routing->getPolyline(...$coordinates));
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaskList) {
            $this->calculate($entity);
        }
    }

    /**
     * Performs TaskCollection calculations when items have been modified.
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $this->taskLists = [];

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

        $taskLists = [];
        foreach ($taskListItems as $taskListItem) {

            $taskList = $taskListItem->getParent();

            // When a taskListItem has been removed, its parent is NULL.
            if (!$taskList) {
                $entityChangeSet = $uow->getEntityChangeSet($taskListItem);
                [ $oldValue, $newValue ] = $entityChangeSet['parent'];
                $taskList = $oldValue;
            }

            // WARNING
            // Do not use in_array() or array_search()
            // It causes error "Nesting level too deep - recursive dependency?"
            $oid = spl_object_hash($taskList);
            if (!isset($taskLists[$oid])) {
                $taskLists[$oid] = $taskList;
            }
        }

        foreach ($taskLists as $taskList) {

            $this->logger->debug('TaskList was modified, recalculating…');
            $this->calculate($taskList);

            if ($uow->isInIdentityMap($taskList)) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(TaskList::class), $taskList);
            }

            $this->taskLists[] = $taskList;
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
            $this->eventBus->handle(new TaskListUpdated($taskList));
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
