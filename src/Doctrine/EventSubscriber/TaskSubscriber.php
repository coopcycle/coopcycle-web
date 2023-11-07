<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\OrderManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
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
    private $tasksToUpdate = [];
    private $postFlushEvents = [];
    private $createdAddresses;

    public function __construct(
        MessageBus $eventBus,
        EventStore $eventStore,
        EntityChangeSetProcessor $processor,
        LoggerInterface $logger,
        Geocoder $geocoder,
        private OrderManager $orderManager
    )
    {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->processor = $processor;
        $this->logger = $logger;
        $this->geocoder = $geocoder;

        $this->createdAddresses = new \SplObjectStorage();
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
            $this->eventBus->handle(new TaskCreated($task));
        }

        $this->logger->debug(sprintf('There are %d "task:updated" events to handle', count($this->tasksToUpdate)));
        $this->handleStateChangesForTasks($em, $this->tasksToUpdate);

        $this->logger->debug(sprintf('There are %d more events to handle', count($this->postFlushEvents)));
        foreach ($this->postFlushEvents as $postFlushEvent) {
            $this->logger->debug(sprintf('Handling event %s', $postFlushEvent::messageName()));
            $this->eventBus->handle($postFlushEvent);
        }

        $this->createdTasks = [];
        $this->postFlushEvents = [];
        $this->processor->eraseMessages();
    }

    /**
     * When a task's address is modified, we need to create a new address, instead of updating the existing address.
     * See https://github.com/coopcycle/coopcycle-web/issues/3306
     *
     * @param UnitOfWork $uow
     * @param array $tasksToUpdate
     * @param \SplObjectStorage $createdAddresses
     */
    private function handleAddressesChangesForTasks(/* UnitOfWork */ $uow, array $tasksToUpdate, \SplObjectStorage $createdAddresses)
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

    /**
     * @param EntityManagerInterface $em
     * @param Task[] $tasksToUpdate
     * @return void
     */
    private function handleStateChangesForTasks(EntitymanagerInterface $em, array $tasksToUpdate): void
    {
        $uow = $em->getUnitOfWork();
        foreach ($tasksToUpdate as $taskToUpdate) {
            $changeset = $uow->getEntityChangeSet($taskToUpdate);
            if (isset($changeset['status']) && $changeset['status'][1] === Task::STATUS_CANCELLED) {
                $delivery = $taskToUpdate->getDelivery();
                if ($delivery !== null && ($order = $delivery->getOrder()) !== null) {
                    $tasks = $delivery->getTasks();
                    $cancelOrder = true;
                    foreach ($tasks as $task) {
                        if ($task->getId() !== $taskToUpdate->getId() && $task->getStatus() !== Task::STATUS_CANCELLED) {
                            $cancelOrder = false;
                            break;

                        }
                    }
                    if ($cancelOrder) {
                        $this->orderManager->cancel($order, 'All tasks were cancelled');
                        $em->flush();
                    }
                }
            }
        }
    }
}
