<?php

namespace Tests\AppBundle\Doctrine\EventSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;
use AppBundle\Service\Geocoder;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


// Avoid error "Returning by reference not supported" with Prophecy
class UnitOfWork
{
    public function getScheduledEntityInsertions()
    {}

    public function getScheduledEntityUpdates()
    {}

    public function getEntityChangeSet($entity)
    {}

    public function computeChangeSets()
    {}

    public function isScheduledForInsert($entity)
    {}
}

class TaskSubscriberTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->eventBus = $this->prophesize(MessageBus::class);

        $this->taskListRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->entityManager
            ->getRepository(TaskList::class)
            ->willReturn($this->taskListRepository->reveal());

        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $requestStack = $this->prophesize(RequestStack::class);

        $eventStore = new EventStore($tokenStorage->reveal(), $requestStack->reveal());

        $taskListProvider = new TaskListProvider($this->entityManager->reveal());
        $changeSetProcessor = new EntityChangeSetProcessor($taskListProvider);

        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->orderManager = $this->prophesize(OrderManager::class);

        $this->subscriber = new TaskSubscriber(
            $this->eventBus->reveal(),
            $eventStore,
            $changeSetProcessor,
            new NullLogger(),
            $this->geocoder->reveal(),
            $this->orderManager->reveal()
        );
    }

    public function testOnFlushWithNewTask()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $task = new Task();

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([
                $task
            ]);
        $unitOfWork
            ->isScheduledForInsert($task)
            ->willReturn(true);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager->getUnitOfWork()->willReturn($unitOfWork->reveal());

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->assertCount(1, $task->getEvents());
        $this->eventBus
            ->handle(Argument::type(TaskCreated::class))
            ->shouldHaveBeenCalledTimes(1);

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }

    public function testOnFlushWithNewAssignedTaskAndNonExistingTaskList()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $user = new User();
        $user->setUsername('bob');
        $user->addRole('ROLE_COURIER');

        $task = new Task();
        $task->assignTo($user);
        $task->setBefore(new \DateTime('2020-04-17 19:00:00'));

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([
                $task
            ]);
        $unitOfWork
            ->isScheduledForInsert($task)
            ->willReturn(true);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(2);

        $this->entityManager
            ->getUnitOfWork()
            ->willReturn($unitOfWork->reveal());

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->eventBus
            ->handle(Argument::type(TaskCreated::class))
            ->shouldHaveBeenCalledTimes(1);
        $this->eventBus
            ->handle(Argument::type(TaskAssigned::class))
            ->shouldHaveBeenCalledTimes(1);

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }

    public function testOnFlushWithNewAssignedTaskAndExistingTaskList()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $date = new \DateTime('2020-04-17 19:00:00');

        $user = new User();
        $user->setUsername('bob');
        $user->addRole('ROLE_COURIER');

        $task = new Task();
        $task->assignTo($user);
        $task->setBefore($date);

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([
                $task
            ]);
        $unitOfWork
            ->isScheduledForInsert($task)
            ->willReturn(true);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(2);

        $this->entityManager
            ->getUnitOfWork()
            ->willReturn($unitOfWork->reveal());

        $taskList = new TaskList();
        $taskList->setCourier($user);
        $taskList->setDate($date);

        $this->taskListRepository
            ->findOneBy([
                'date' => $date,
                'courier' => $user,
            ])
            ->willReturn($taskList);

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldNotBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->eventBus
            ->handle(Argument::type(TaskCreated::class))
            ->shouldHaveBeenCalledTimes(1);
        $this->eventBus
            ->handle(Argument::type(TaskAssigned::class))
            ->shouldHaveBeenCalledTimes(1);

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }

    public function testOnFlushWithAssignedTask()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $user = new User();
        $user->setUsername('bob');

        $task = new Task();
        $task->setDoneBefore(new \DateTime('2019-11-21 19:00:00'));

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->isScheduledForInsert($task)
            ->willReturn(false);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([
                $task
            ]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager->getUnitOfWork()->willReturn($unitOfWork->reveal());

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->assertCount(0, $task->getEvents());
        $this->eventBus
            ->handle(Argument::type(TaskCreated::class))
            ->shouldNotHaveBeenCalled();
        $this->eventBus
            ->handle(Argument::type(TaskAssigned::class))
            ->shouldHaveBeenCalledTimes(1);

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }

    public function testOnFlushWithSeveralAssignedTasksToSameUser()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $user = new User();
        $user->setUsername('bob');

        $task1 = new Task();
        $task1->setDoneBefore(new \DateTime('2019-11-21 19:00:00'));

        $task2 = new Task();
        $task2->setDoneBefore(new \DateTime('2019-11-21 21:00:00'));

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->isScheduledForInsert($task1)
            ->willReturn(false);
        $unitOfWork
            ->isScheduledForInsert($task2)
            ->willReturn(false);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([
                $task1,
                $task2,
            ]);
        $unitOfWork
            ->getEntityChangeSet($task1)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->getEntityChangeSet($task2)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager->getUnitOfWork()->willReturn($unitOfWork->reveal());

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->assertCount(0, $task1->getEvents());
        $this->assertCount(0, $task2->getEvents());

        $this->eventBus
            ->handle(Argument::type(TaskCreated::class))
            ->shouldNotHaveBeenCalled();
    }

    /**
     * When a task is assigned to a user with role ROLE_ADMIN,
     * it should NOT send a push notification
     */
    public function testOnFlushWithAssignedTaskToAdmin()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $user = new User();
        $user->setUsername('bob');
        $user->addRole('ROLE_ADMIN');

        $task = new Task();
        $task->setDoneBefore(new \DateTime('2019-11-21 19:00:00'));

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->isScheduledForInsert($task)
            ->willReturn(false);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([
                $task
            ]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager->getUnitOfWork()->willReturn($unitOfWork->reveal());

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->assertCount(0, $task->getEvents());
        $this->eventBus
            ->handle(Argument::type(TaskCreated::class))
            ->shouldNotHaveBeenCalled();
        $this->eventBus
            ->handle(Argument::type(TaskAssigned::class))
            ->shouldHaveBeenCalledTimes(1);
    }

    public function testOnFlushWithUnassignedTask()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $date = new \DateTime('2020-04-17 19:00:00');

        $user = new User();
        $user->setUsername('bob');
        $user->addRole('ROLE_COURIER');

        $task = new Task();
        $task->setBefore($date);

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->isScheduledForInsert($task)
            ->willReturn(false);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([
                $task
            ]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([
                'assignedTo' => [ $user, null ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager
            ->getUnitOfWork()
            ->willReturn($unitOfWork->reveal());

        $taskList = new TaskList();
        $taskList->setCourier($user);
        $taskList->setDate($date);

        $this->taskListRepository
            ->findOneBy([
                'date' => $date,
                'courier' => $user,
            ])
            ->willReturn($taskList);

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldNotBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->eventBus
            ->handle(Argument::type(TaskUnassigned::class))
            ->shouldHaveBeenCalledTimes(1);

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($task)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }

    public function testOnFlushWithUnassignedLinkedTasks()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $date = new \DateTime('2020-04-17 19:00:00');

        $user = new User();
        $user->setUsername('bob');
        $user->addRole('ROLE_COURIER');

        $pickup = new Task();
        $pickup->setBefore($date);
        $pickup->assignTo($user);

        $dropoff = new Task();
        $dropoff->setBefore($date);
        $dropoff->assignTo($user);

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->isScheduledForInsert($pickup)
            ->willReturn(false);
        $unitOfWork
            ->isScheduledForInsert($dropoff)
            ->willReturn(false);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([ $pickup ]);
        $unitOfWork
            ->getEntityChangeSet($pickup)
            ->willReturn([
                'assignedTo' => [ $user, null ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager
            ->getUnitOfWork()
            ->willReturn($unitOfWork->reveal());

        $taskList = new TaskList();
        $taskList->setCourier($user);
        $taskList->setDate($date);
        $taskList->setTasks([ $pickup, $dropoff ]);

        $this->taskListRepository
            ->findOneBy([
                'date' => $date,
                'courier' => $user,
            ])
            ->willReturn($taskList);

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldNotBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->eventBus
            ->handle(Argument::type(TaskUnassigned::class))
            ->shouldHaveBeenCalledTimes(1);

        $this->assertFalse($pickup->isAssigned());
        $this->assertTrue($dropoff->isAssigned());

        $this->assertFalse($taskList->containsTask($pickup));
        $this->assertTrue($taskList->containsTask($dropoff));

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($pickup)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }

    public function testOnFlushWithAssignedLinkedTasks()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);

        $date = new \DateTime('2020-04-17 19:00:00');

        $user = new User();
        $user->setUsername('bob');
        $user->addRole('ROLE_COURIER');

        $pickup = new Task();
        $pickup->setBefore($date);
        $pickup->assignTo($user);

        $dropoff = new Task();
        $dropoff->setBefore($date);

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->isScheduledForInsert($pickup)
            ->willReturn(false);
        $unitOfWork
            ->isScheduledForInsert($dropoff)
            ->willReturn(false);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([ $pickup ]);
        $unitOfWork
            ->getEntityChangeSet($pickup)
            ->willReturn([
                'assignedTo' => [ null, $user ]
            ]);
        $unitOfWork
            ->computeChangeSets()
            ->shouldBeCalledTimes(1);

        $this->entityManager
            ->getUnitOfWork()
            ->willReturn($unitOfWork->reveal());

        $taskList = new TaskList();
        $taskList->setCourier($user);
        $taskList->setDate($date);

        $this->taskListRepository
            ->findOneBy([
                'date' => $date,
                'courier' => $user,
            ])
            ->willReturn($taskList);

        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldNotBeCalled();

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );

        $this->eventBus
            ->handle(Argument::type(TaskAssigned::class))
            ->shouldHaveBeenCalledTimes(1);

        $this->assertTrue($pickup->isAssigned());
        $this->assertFalse($dropoff->isAssigned());

        $this->assertTrue($taskList->containsTask($pickup));
        $this->assertFalse($taskList->containsTask($dropoff));

        // Make sure it can be called several
        // times during the same request cycle

        $unitOfWork
            ->getScheduledEntityInsertions()
            ->willReturn([]);
        $unitOfWork
            ->getScheduledEntityUpdates()
            ->willReturn([]);
        $unitOfWork
            ->getEntityChangeSet($pickup)
            ->willReturn([]);

        $this->subscriber->onFlush(
            new OnFlushEventArgs($this->entityManager->reveal())
        );
        $this->subscriber->postFlush(
            new PostFlushEventArgs($this->entityManager->reveal())
        );
    }
}
