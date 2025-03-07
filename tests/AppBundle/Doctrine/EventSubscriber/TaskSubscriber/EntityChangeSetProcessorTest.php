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

        $this->assertCount(0, $processor->recordedMessages());
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

        $this->assertCount(1, $processor->recordedMessages());
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

        $this->assertCount(1, $processor->recordedMessages());

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

        $this->assertCount(1, $processor->recordedMessages());

        $this->assertFalse($taskListForBob->containsTask($task));
    }
}
