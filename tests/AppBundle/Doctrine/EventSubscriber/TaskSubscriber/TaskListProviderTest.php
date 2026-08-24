<?php

namespace Tests\AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskListRepository;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Clock\MockClock;

class TaskListProviderTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $taskListRepository;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->taskListRepository = $this->prophesize(TaskListRepository::class);

        $this->entityManager
            ->getRepository(TaskList::class)
            ->willReturn($this->taskListRepository->reveal());

        $this->entityManager->persist(Argument::type(TaskList::class))->willReturn(null);
    }

    private function provider(string $now): TaskListProvider
    {
        return new TaskListProvider($this->entityManager->reveal(), new MockClock($now));
    }

    private function multiDayTask(): Task
    {
        $task = new Task();
        $task->setAfter(new \DateTime('2026-07-17 10:00:00'));
        $task->setBefore(new \DateTime('2026-08-17 18:00:00'));

        return $task;
    }

    /**
     * Fix for issue #874.
     *
     * A month-spanning task that is not in any list yet must be filed on the
     * dispatch day (clamped to its own [doneAfter, doneBefore] window), NOT
     * blindly on its doneBefore (last) day.
     */
    public function testNotYetListedTaskIsFiledOnDispatchDayClampedToWindow()
    {
        $courier = new User();
        $courier->setUsername('bob');

        $task = $this->multiDayTask();

        // The task is not in any list yet.
        $this->taskListRepository
            ->findTaskListContainingTask($task, $courier)
            ->willReturn(null);
        $this->taskListRepository->findOneBy(Argument::any())->willReturn(null);

        // "Today" is before the task's window => clamp up to its first day.
        $taskList = $this->provider('2026-07-10')->getTaskList($task, $courier);

        $this->assertEquals('2026-07-17', $taskList->getDate()->format('Y-m-d'));
        $this->assertNotEquals(
            $task->getDoneBefore()->format('Y-m-d'),
            $taskList->getDate()->format('Y-m-d'),
            'A multi-day task must not be filed onto its doneBefore (last) day (issue #874)'
        );
    }

    /**
     * When "today" falls inside the task's window, the task is filed on today.
     */
    public function testNotYetListedTaskIsFiledOnToday()
    {
        $courier = new User();
        $courier->setUsername('bob');

        $task = $this->multiDayTask();

        $this->taskListRepository
            ->findTaskListContainingTask($task, $courier)
            ->willReturn(null);
        $this->taskListRepository->findOneBy(Argument::any())->willReturn(null);

        $taskList = $this->provider('2026-07-25 09:00:00')->getTaskList($task, $courier);

        $this->assertEquals('2026-07-25', $taskList->getDate()->format('Y-m-d'));
    }

    /**
     * The persisted list that already contains the task is the source of truth:
     * it is returned as-is, regardless of the task's date range, and no new list
     * is created.
     */
    public function testReturnsExistingListContainingTask()
    {
        $courier = new User();
        $courier->setUsername('bob');

        // A persisted task (has an id) is looked up by the containing-list query.
        $task = $this->multiDayTask();
        $idProperty = new \ReflectionProperty(Task::class, 'id');
        $idProperty->setValue($task, 1);

        $existing = new TaskList();
        $existing->setCourier($courier);
        $existing->setDate(new \DateTime('2026-07-20'));

        $this->taskListRepository
            ->findTaskListContainingTask($task, $courier)
            ->willReturn($existing);

        $this->entityManager->persist(Argument::any())->shouldNotBeCalled();

        $taskList = $this->provider('2026-07-25')->getTaskList($task, $courier);

        $this->assertSame($existing, $taskList);
    }
}
