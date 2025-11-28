<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::onFlush, priority: 32, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, priority: 32, connection: 'default')]
class TaskSubscriber
{
    private $eventBus;
    private $eventStore;
    private $processor;
    private $logger;

    private $createdTasks = [];
    private $tasksToUpdate = [];
    private $postFlushEvents = [];
    private $createdAddresses;

    public function __construct(
        MessageBusInterface $eventBus,
        EventStore $eventStore,
        EntityChangeSetProcessor $processor,
        LoggerInterface $logger,
        private Geocoder $geocoder,
    ) {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->processor = $processor;
        $this->logger = $logger;

        $this->createdAddresses = new \SplObjectStorage();
    }

    public function onFlush(OnFlushEventArgs $args)
    {

        // Cleanup
        $this->createdTasks = [];
        $this->postFlushEvents = [];
        $this->processor->eraseMessages();
        $this->createdAddresses = new \SplObjectStorage();

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isTask = function ($entity) {
            return $entity instanceof Task;
        };

        $tasksToInsert = array_filter($uow->getScheduledEntityInsertions(), $isTask);
        $this->tasksToUpdate = array_filter($uow->getScheduledEntityUpdates(), $isTask);

        $this->logger->debug(sprintf('Found %d instances of Task scheduled for insert', count($tasksToInsert)));
        $this->logger->debug(sprintf('Found %d instances of Task scheduled for update', count($this->tasksToUpdate)));

        $this->createdTasks = [];
        foreach ($tasksToInsert as $task) {
            $event = $this->eventStore->createEvent(new TaskCreated($task));
            $task->addEvent($event);
            $this->createdTasks[] = $task;
        }

        if (count($tasksToInsert) > 0) {
            $uow->computeChangeSets();
        }

        $this->handleAddressesChangesForTasks($uow, $this->tasksToUpdate, $this->createdAddresses);
        $tasks = array_merge($tasksToInsert, $this->tasksToUpdate);

        if (count($tasks) === 0) {
            return;
        }

        foreach ($tasks as $task) {
            $this->processor->process($task, $uow->getEntityChangeSet($task));
        }

        foreach ($this->tasksToUpdate as $task) {
            $changeset = $uow->getEntityChangeSet($task);

            $isOnlyStatusChange = count($changeset) === 1 && isset($changeset['status']);
            // If the only change is the status, and we should already have an event for that,
            // so we don't need to emit a "task:updated" event
            if ($isOnlyStatusChange) {
                continue;
            }

            $domainEvent = new TaskUpdated($task);
            $taskEvent = $this->eventStore->createEvent($domainEvent);
            $task->addEvent($taskEvent);
            $this->postFlushEvents[] = $domainEvent;
        }

        if (count($this->tasksToUpdate) > 0) {
            $uow->computeChangeSets();
        }

        foreach ($this->processor->recordedMessages as $recordedMessage) {
            // If the task is not persisted yet (i.e entity insertions),
            // we handle the event in postFlush
            if ($uow->isScheduledForInsert($recordedMessage->getTask())) {
                $this->postFlushEvents[] = $recordedMessage;
                continue;
            }
            $this->eventBus->dispatch($recordedMessage);
        }

        if (count($this->processor->recordedMessages) > 0) {
            $uow->computeChangeSets();
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        foreach ($this->createdAddresses as $address) {
            $em->persist($address);
            $item = $this->createdAddresses[$address];
            $task = $item['task'];
            $task->setAddress($address);
            $em->flush();
        }

        $this->logger->debug(sprintf('There are %d "task:created" events to handle', count($this->createdTasks)));
        foreach ($this->createdTasks as $task) {
            $this->eventBus->dispatch(new TaskCreated($task));
        }

        $this->logger->debug(sprintf('There are %d "task:updated" events to handle', count($this->tasksToUpdate)));

        $this->logger->debug(sprintf('There are %d more events to handle', count($this->postFlushEvents)));
        foreach ($this->postFlushEvents as $postFlushEvent) {
            $this->logger->debug(sprintf('Handling event %s', $postFlushEvent::messageName()));
            $this->eventBus->dispatch($postFlushEvent);
        }

        $this->createdTasks = [];
        $this->tasksToUpdate = [];
        $this->postFlushEvents = [];
        $this->processor->eraseMessages();
    }

    /**
     * When a task's address is modified, we need to create a new address, instead of updating the existing address.
     * See https://github.com/coopcycle/coopcycle-web/issues/3306
     *
     * @param UnitOfWork $uow
     */
    private function handleAddressesChangesForTasks(/* UnitOfWork */$uow, array $tasksToUpdate, \SplObjectStorage $createdAddresses)
    {
        $isAddress = function ($entity) {
            return $entity instanceof Address;
        };

        $addressesToUpdate = array_filter($uow->getScheduledEntityUpdates(), $isAddress);

        if (count($tasksToUpdate) > 0 && count($addressesToUpdate) > 0) {

            foreach ($tasksToUpdate as $task) {
                $index = array_search($task->getAddress(), $addressesToUpdate);

                if ($index) {
                    $taskAddress = $addressesToUpdate[$index];
                    $entityChangeset = $uow->getEntityChangeSet($taskAddress);

                    if (isset($entityChangeset['streetAddress'])) {
                        [$oldValue, $newValue] = $entityChangeset['streetAddress'];
                        if (!empty($oldValue) && !empty($newValue) && $oldValue !== $newValue) {
                            $taskAddress = $this->geocoder->geocode($newValue, $taskAddress);

                            $newAddress = $taskAddress->clone();

                            $uow->detach($taskAddress); // do not impact updates on previous task

                            $createdAddresses[$newAddress] = [
                                'task' => $task
                            ];
                        }
                    }
                }
            }
        }
    }
}
