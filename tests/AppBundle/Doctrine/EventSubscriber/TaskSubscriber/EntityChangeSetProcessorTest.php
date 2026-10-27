<?php

namespace Tests\AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\EntityChangeSetProcessor;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\User;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Entity\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class EntityChangeSetProcessorTest extends TestCase
{
    use ProphecyTrait;

    private $taskListProvider;

    private $tourRepository;

    private $entityManager;

    public function setUp(): void
    {
        $this->taskListProvider = $this->prophesize(TaskListProvider::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->tourRepository = $this->prophesize(TourRepository::class);
        $this->entityManager
            ->getRepository(Tour::class)
            ->willReturn($this->tourRepository->reveal());
    }

    public function testTaskAssignmentHasNotChanged()
    {
        $user = new User();

        $task = new Task();

        $taskList = new TaskList();
        $taskList->setCourier($user);

        $this->taskListProvider
            ->getTaskList($task, $user)
            ->willReturn($taskList);

        $this->assertFalse($taskList->containsTask($task));

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal(), null, $this->entityManager->reveal());
        $processor->process($task, []);

        $this->assertCount(0, $processor->recordedMessages);
    }

    public function testTaskWasAssigned()
    {
        $user = new User();

        $task = new Task();

        $taskList = new TaskList();
        $taskList->setCourier($user);

        $this->taskListProvider
            ->getTaskList($task, $user)
            ->willReturn($taskList);

        $this->assertFalse($taskList->containsTask($task));

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal(), null, $this->entityManager->reveal());
        $processor->process($task, [
            'assignedTo' => [ null, $user ]
        ]);

        $this->assertCount(1, $processor->recordedMessages);
        $this->assertCount(1, $taskList->getTasks());
        $this->assertTrue($taskList->containsTask($task));
    }

    public function testTaskWasReassigned()
    {
        $bob = new User();
        $bob->setUsername('bob');

        $claire = new User();
        $claire->setUsername('claire');

        $task = new Task();

        $taskListForBob = new TaskList();
        $taskListForBob->setCourier($bob);

        $item = new Item();
        $item->setTask($task);
        $item->setPosition(count($taskListForBob->getItems()));
        $taskListForBob->addItem($item);

        $taskListForClaire = new TaskList();
        $taskListForClaire->setCourier($claire);

        $this->taskListProvider
            ->getTaskList($task, $bob)
            ->willReturn($taskListForBob);

        $this->taskListProvider
            ->getTaskList($task, $claire)
            ->willReturn($taskListForClaire);

        $this->assertTrue($taskListForBob->containsTask($task));
        $this->assertFalse($taskListForClaire->containsTask($task));

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal(), null, $this->entityManager->reveal());
        $processor->process($task, [
            'assignedTo' => [ $bob, $claire ]
        ]);

        $this->assertCount(1, $processor->recordedMessages);

        $this->assertFalse($taskListForBob->containsTask($task));
        $this->assertTrue($taskListForClaire->containsTask($task));
    }

    public function testTaskWasUnassigned()
    {
        $bob = new User();
        $bob->setUsername('bob');

        $task = new Task();

        $taskListForBob = new TaskList();
        $taskListForBob->setCourier($bob);

        $item = new Item();
        $item->setTask($task);
        $item->setPosition(count($taskListForBob->getItems()));
        $taskListForBob->addItem($item);

        $this->taskListProvider
            ->getTaskList($task, $bob)
            ->willReturn($taskListForBob);

        $this->assertTrue($taskListForBob->containsTask($task));

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal(), null, $this->entityManager->reveal());
        $processor->process($task, [
            'assignedTo' => [ $bob, null ]
        ]);

        $this->assertCount(1, $processor->recordedMessages);

        $this->assertFalse($taskListForBob->containsTask($task));
    }

    public function testAssignedTaskDateChangedToAnotherDay()
    {
        $bob = new User();
        $bob->setUsername('bob');

        $task = new Task();
        $task->assignTo($bob);
        $task->setAfter(new \DateTime('2026-10-26 10:00'));
        $task->setBefore(new \DateTime('2026-10-26 11:45'));

        $taskListOn27 = new TaskList();
        $taskListOn27->setCourier($bob);
        $taskListOn27->setDate(new \DateTime('2026-10-27'));

        $item = new Item();
        $item->setTask($task);
        $item->setPosition(0);
        $taskListOn27->addItem($item);

        $taskListOn26 = new TaskList();
        $taskListOn26->setCourier($bob);
        $taskListOn26->setDate(new \DateTime('2026-10-26'));

        $this->taskListProvider
            ->getTaskList($task, $bob)
            ->willReturn($taskListOn27);

        $this->taskListProvider
            ->getTaskListForAssignmentDate($task, $bob)
            ->willReturn($taskListOn26);

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal());
        $processor->process($task, [
            'doneAfter' => [ new \DateTime('2026-10-27 10:00'), $task->getAfter() ],
            'doneBefore' => [ new \DateTime('2026-10-27 11:45'), $task->getBefore() ],
        ]);

        $this->assertCount(0, $processor->recordedMessages);

        $this->assertFalse($taskListOn27->containsTask($task));
        $this->assertTrue($taskListOn26->containsTask($task));
    }

    public function testAssignedTaskDateChangedWithinTheSameDay()
    {
        $bob = new User();
        $bob->setUsername('bob');

        $task = new Task();
        $task->assignTo($bob);
        $task->setAfter(new \DateTime('2026-10-26 14:00'));
        $task->setBefore(new \DateTime('2026-10-26 15:00'));

        $taskList = new TaskList();
        $taskList->setCourier($bob);
        $taskList->setDate(new \DateTime('2026-10-26'));

        $item = new Item();
        $item->setTask($task);
        $item->setPosition(0);
        $taskList->addItem($item);

        $this->taskListProvider
            ->getTaskList($task, $bob)
            ->willReturn($taskList);

        $this->taskListProvider
            ->getTaskListForAssignmentDate($task, $bob)
            ->shouldNotBeCalled();

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal());
        $processor->process($task, [
            'doneAfter' => [ new \DateTime('2026-10-26 10:00'), $task->getAfter() ],
            'doneBefore' => [ new \DateTime('2026-10-26 11:00'), $task->getBefore() ],
        ]);

        $this->assertTrue($taskList->containsTask($task));
    }

    public function testAssignedTaskInTourDateChangedIsNotMoved()
    {
        $bob = new User();
        $bob->setUsername('bob');

        $task = new Task();
        $task->assignTo($bob);
        $task->setAfter(new \DateTime('2026-10-26 10:00'));
        $task->setBefore(new \DateTime('2026-10-26 11:45'));

        $tour = new Tour();
        $tour->addTask($task);

        $taskListOn27 = new TaskList();
        $taskListOn27->setCourier($bob);
        $taskListOn27->setDate(new \DateTime('2026-10-27'));

        $item = new Item();
        $item->setTour($tour);
        $item->setPosition(0);
        $taskListOn27->addItem($item);

        $this->taskListProvider
            ->getTaskList($task, $bob)
            ->willReturn($taskListOn27);

        $this->taskListProvider
            ->getTaskListForAssignmentDate($task, $bob)
            ->shouldNotBeCalled();

        $processor = new EntityChangeSetProcessor($this->taskListProvider->reveal());
        $processor->process($task, [
            'doneAfter' => [ new \DateTime('2026-10-27 10:00'), $task->getAfter() ],
            'doneBefore' => [ new \DateTime('2026-10-27 11:45'), $task->getBefore() ],
        ]);

        $this->assertTrue($taskListOn27->containsTask($task));
    }
}
