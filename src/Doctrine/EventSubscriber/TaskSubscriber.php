<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Entity\Task;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskSubscriber implements EventSubscriber
{
    private $eventBus;
    private $eventStore;
    private $processor;
    private $logger;

    private $createdTasks = [];
    private $postFlushEvents = [];

    public function __construct(
        MessageBus $eventBus,
        EventStore $eventStore,
        EntityChangeSetProcessor $processor,
        LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->processor = $processor;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        // Cleanup
        $this->createdTasks = [];
        $this->postFlushEvents = [];
        $this->processor->eraseMessages();

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isTask = function ($entity) {
            return $entity instanceof Task;
        };

        $tasksToInsert = array_filter($uow->getScheduledEntityInsertions(), $isTask);
        $tasksToUpdate = array_filter($uow->getScheduledEntityUpdates(), $isTask);

        $this->logger->debug(sprintf('Found %d instances of Task scheduled for insert', count($tasksToInsert)));
        $this->logger->debug(sprintf('Found %d instances of Task scheduled for update', count($tasksToUpdate)));

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
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $this->logger->debug(sprintf('There are %d "task:created" events to handle', count($this->createdTasks)));
        foreach ($this->createdTasks as $task) {
            $this->eventBus->handle(new TaskCreated($task));
        }

        $this->logger->debug(sprintf('There are %d more events to handle', count($this->postFlushEvents)));
        foreach ($this->postFlushEvents as $postFlushEvent) {
            $this->logger->debug(sprintf('Handling event %s', $postFlushEvent::messageName()));
            $this->eventBus->handle($postFlushEvent);
        }

        $this->createdTasks = [];
        $this->postFlushEvents = [];
        $this->processor->eraseMessages();
    }
}
