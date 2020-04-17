<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\Task;
use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use FOS\UserBundle\Model\UserInterface;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskSubscriber implements EventSubscriber
{
    private $eventBus;
    private $eventStore;
    private $messageBus;
    private $processor;
    private $translator;
    private $logger;

    private $createdTasks = [];
    private $postFlushEvents = [];
    private $usersToNotify;

    public function __construct(
        MessageBus $eventBus,
        EventStore $eventStore,
        MessageBusInterface $messageBus,
        EntityChangeSetProcessor $processor,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->messageBus = $messageBus;
        $this->processor = $processor;
        $this->translator = $translator;
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
        // Cleanup
        $this->createdTasks = [];
        $this->postFlushEvents = [];
        $this->usersToNotify = [];
        $this->processor->eraseMessages();

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

        foreach ($tasks as $task) {
            $this->processor->process($task, $uow->getEntityChangeSet($task));
        }

        foreach ($this->processor->recordedMessages() as $recordedMessage) {
            // If the task is not persisted yet (i.e entity insertions),
            // we handle the event in postFlush
            if ($uow->isScheduledForInsert($recordedMessage->getTask())) {
                $this->postFlushEvents[] = $recordedMessage;
                continue;
            }
            $this->eventBus->handle($recordedMessage);
        }

        if (count($this->processor->recordedMessages()) > 0) {
            $uow->computeChangeSets();
        }

        $this->usersToNotify = new \SplObjectStorage();
        foreach ($this->processor->recordedMessages() as $message) {
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
        $this->debug(sprintf('There are %d "task:created" events to handle', count($this->createdTasks)));
        foreach ($this->createdTasks as $task) {
            $this->eventBus->handle(new TaskCreated($task));
        }

        $this->debug(sprintf('There are %d more events to handle', count($this->postFlushEvents)));
        foreach ($this->postFlushEvents as $postFlushEvent) {
            $this->debug(sprintf('Handling event %s', $postFlushEvent::messageName()));
            $this->eventBus->handle($postFlushEvent);
        }

        if (count($this->usersToNotify) > 0) {

            foreach ($this->usersToNotify as $date) {

                $users = $this->usersToNotify[$date];
                $users = array_unique($users);

                // We do not send push notifications to users with role ROLE_ADMIN,
                // they have WebSockets to get live updates
                $users = array_filter($users, function (UserInterface $user) {
                    return !$user->hasRole('ROLE_ADMIN');
                });

                if (count($users) === 0) {
                    continue;
                }

                $usernames = array_map(function ($user) {
                    return $user->getUsername();
                }, $users);

                $data = [
                    'event' => [
                        'name' => 'tasks:changed',
                        'data' => [
                            'date' => $date->format('Y-m-d')
                        ]
                    ]
                ];

                $message = $this->translator->trans('notifications.tasks_changed', [
                    '%date%' => $date->format('Y-m-d'),
                ]);

                if (RemotePushNotificationManager::isEnabled()) {
                    $this->messageBus->dispatch(
                        new PushNotification($message, $usernames, $data)
                    );
                }
            }
        }

        $this->createdTasks = [];
        $this->postFlushEvents = [];
        $this->usersToNotify = [];
        $this->processor->eraseMessages();
    }
}
