<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\Task;
use AppBundle\Service\RemotePushNotificationManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;

class TaskSubscriber implements EventSubscriber
{
    private $eventBus;
    private $eventStore;
    private $remotePushNotificationManager;
    private $logger;
    private $createdTasks = [];
    private $usersToNotify;

    public function __construct(
        MessageBus $eventBus,
        EventStore $eventStore,
        RemotePushNotificationManager $remotePushNotificationManager,
        LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->logger = $logger;
        $this->usersToNotify = new \SplObjectStorage();
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    private function debug($message)
    {
        $this->logger->debug(sprintf('TaskSubscriber :: %s', $message));
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isTask = function ($entity) {
            return $entity instanceof Task;
        };

        $tasksToInsert = array_filter($uow->getScheduledEntityInsertions(), $isTask);
        $tasksToUpdate = array_filter($uow->getScheduledEntityUpdates(), $isTask);

        $this->debug(sprintf('Found %d instances of Task scheduled for insert', count($tasksToInsert)));
        $this->debug(sprintf('Found %d instances of Task scheduled for update', count($tasksToUpdate)));

        $this->createdTasks = [];
        foreach ($tasksToInsert as $task) {
            $event = $this->eventStore->createEvent(new TaskCreated($task));
            $task->addEvent($event);
            $this->createdTasks[] = $task;
        }

        if (count($tasksToInsert) > 0) {
            $uow->computeChangeSets();
        }

        $tasks = array_merge($tasksToInsert, $tasksToUpdate);

        if (count($tasks) === 0) {
            return;
        }

        $provider = new TaskListProvider($em);

        $allMessages = [];

        foreach ($tasks as $task) {

            $processor = new EntityChangeSetProcessor($provider, $this->logger);
            $processor->process($task, $uow->getEntityChangeSet($task));

            $messages = $processor->recordedMessages();

            if (count($messages) > 0) {
                foreach ($messages as $message) {
                    $this->eventBus->handle($message);
                }

                $uow->computeChangeSets();
            }

            $allMessages = array_merge($allMessages, $messages);
        }

        $this->usersToNotify = new \SplObjectStorage();
        foreach ($allMessages as $message) {
            if ($message instanceof TaskAssigned || $message instanceof TaskUnassigned) {
                // FIXME
                // Using $task->getDoneBefore() causes problems with tasks spanning over several days
                // Here it would send a notification for the wrong day
                // @see https://github.com/coopcycle/coopcycle-web/issues/874
                $date = $task->getDoneBefore();
                $users = isset($this->usersToNotify[$date]) ? $this->usersToNotify[$date] : [];
                $this->usersToNotify[$date] = array_merge($users, [ $message->getUser() ]);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        foreach ($this->createdTasks as $task) {
            $this->eventBus->handle(new TaskCreated($task));
        }

        if (count($this->usersToNotify) > 0) {

            foreach ($this->usersToNotify as $date) {

                $users = $this->usersToNotify[$date];
                $users = array_unique($users);

                $data = [
                    'event' => [
                        'name' => 'tasks:changed',
                        'data' => [
                            'date' => $date->format('Y-m-d')
                        ]
                    ]
                ];

                // TODO Translate
                $message = sprintf('Tasks for %s changed!', $date->format('Y-m-d'));

                $this->remotePushNotificationManager->send($message, $users, $data);
            }
        }
    }
}
