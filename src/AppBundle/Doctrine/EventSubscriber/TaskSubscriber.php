<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\Task;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;

class TaskSubscriber implements EventSubscriber
{
    private $eventBus;
    private $routing;
    private $logger;
    private $createdTasks = [];

    public function __construct(MessageBus $eventBus, EventStore $eventStore, LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->logger = $logger;
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
            $task->getEvents()->add($event);
            $this->createdTasks[] = $task;
        }

        if (count($tasksToInsert) > 0) {
            $uow->computeChangeSets();
        }

        $tasks = array_merge($tasksToInsert, $tasksToUpdate);

        $provider = new TaskListProvider($em);

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
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($this->createdTasks as $task) {
            $this->eventBus->handle(new TaskCreated($task));
        }
    }
}
