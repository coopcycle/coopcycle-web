<?php

namespace Tests\AppBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\User;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

class TaskListManagerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $iriConverter;
    private $logger;
    private $taskListManager;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->iriConverter = $this->prophesize(IriConverterInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->taskListManager = new TaskListManager(
            $this->entityManager->reveal(),
            $this->iriConverter->reveal(),
            $this->logger->reveal()
        );
    }

    /**
     * Helper: build a task assigned to $courier on $date, with a given status.
     */
    private function assignedTask(User $courier, \DateTime $date, string $iri, string $status = Task::STATUS_TODO): Task
    {
        $task = new Task();
        $task->setStatus($status);
        $task->assignTo($courier);

        // set_items resolves item IRIs through the IriConverter
        $this->iriConverter->getIriFromResource($task)->willReturn($iri);
        $this->iriConverter->getResourceFromIri($iri)->willReturn($task);

        return $task;
    }

    private function taskListWith(User $courier, \DateTime $date, array $tasks): TaskList
    {
        $taskList = new TaskList();
        $taskList->setCourier($courier);
        $taskList->setDate($date);

        foreach (array_values($tasks) as $position => $task) {
            $item = new Item();
            $item->setTask($task);
            $item->setPosition($position);
            $taskList->addItem($item);
        }

        return $taskList;
    }

    /**
     * Reproduces issue #5249.
     *
     * A month-spanning task is DONE and still assigned to the courier. The
     * dispatcher later edits that courier's list; the client PUTs a set_items
     * payload that no longer contains the completed task. set_items uses
     * destructive PUT semantics, so the DONE task is silently unassigned.
     *
     * Expected invariant: a completed task must never be unassigned by set_items.
     */
    public function testSetItemsMustNotUnassignCompletedTask()
    {
        $courier = new User();
        $courier->setUsername('bob');

        $date = new \DateTime('2026-07-17');

        $doneTask  = $this->assignedTask($courier, $date, '/api/tasks/1', Task::STATUS_DONE);
        $otherTask = $this->assignedTask($courier, $date, '/api/tasks/2', Task::STATUS_TODO);

        $taskList = $this->taskListWith($courier, $date, [$doneTask, $otherTask]);

        $this->assertTrue($doneTask->isAssigned());

        // The client's new payload omits the completed task (it is no longer an
        // "active" item in the app's view / cache), keeping only the other task.
        $this->taskListManager->assign($taskList, ['/api/tasks/2']);

        $this->assertTrue(
            $doneTask->isAssigned(),
            'A completed (DONE) task must not be unassigned by a set_items call that omits it'
        );
        $this->assertTrue($doneTask->isAssignedTo($courier));
    }

    /**
     * A task list is a courier's planning for one day. A task whose
     * [doneAfter, doneBefore] window does not cover that day cannot be carried
     * out then, and must not be filed on the list.
     *
     * When it is, the task ends up with `assignedTo` set but no item on the
     * list of the day it is shown on, and disappears from the dispatch board:
     * `selectUnassignedTasks` skips it because it has a courier, and no task
     * list renders it. This is how tasks created on 2026-08-28 ended up on a
     * 2026-08-27 list, invisible on both days.
     */
    public function testSetItemsSkipsTaskFromAnotherDay()
    {
        $courier = new User();
        $courier->setUsername('bob');

        $tomorrowsTask = $this->assignedTask($courier, new \DateTime('2026-08-28'), '/api/tasks/1');
        $tomorrowsTask->setAfter(new \DateTime('2026-08-28 13:06:00'));
        $tomorrowsTask->setBefore(new \DateTime('2026-08-28 13:16:00'));
        $tomorrowsTask->unassign();

        $taskList = $this->taskListWith($courier, new \DateTime('2026-08-27'), []);

        $this->taskListManager->assign($taskList, ['/api/tasks/1']);

        $this->assertCount(0, $taskList->getItems());
        $this->assertFalse(
            $tomorrowsTask->isAssigned(),
            'A task filed on another day\'s list must be left unassigned, so it stays visible on its own day'
        );
        $this->logger->warning(Argument::containingString('/api/tasks/1'))->shouldHaveBeenCalled();
    }

    /**
     * Only the out-of-range items are dropped; the rest of the payload is
     * applied as usual.
     */
    public function testSetItemsAppliesTheRestOfThePayload()
    {
        $courier = new User();
        $courier->setUsername('bob');

        $date = new \DateTime('2026-08-27');

        $todaysTask = $this->assignedTask($courier, $date, '/api/tasks/1');
        $todaysTask->setAfter(new \DateTime('2026-08-27 09:00:00'));
        $todaysTask->setBefore(new \DateTime('2026-08-27 17:00:00'));

        $tomorrowsTask = $this->assignedTask($courier, $date, '/api/tasks/2');
        $tomorrowsTask->setAfter(new \DateTime('2026-08-28 13:06:00'));
        $tomorrowsTask->setBefore(new \DateTime('2026-08-28 13:16:00'));
        $tomorrowsTask->unassign();

        $taskList = $this->taskListWith($courier, $date, []);

        $this->taskListManager->assign($taskList, ['/api/tasks/1', '/api/tasks/2']);

        $this->assertCount(1, $taskList->getItems());
        $this->assertTrue($taskList->containsTask($todaysTask));
        $this->assertFalse($taskList->containsTask($tomorrowsTask));
        $this->assertFalse($tomorrowsTask->isAssigned());
    }

    /**
     * A window spanning midnight belongs to both days, exactly as
     * `TaskDateFilter` reports it for both.
     */
    public function testSetItemsAcceptsTaskWhoseWindowSpansTheDay()
    {
        $courier = new User();
        $courier->setUsername('bob');

        $task = $this->assignedTask($courier, new \DateTime('2026-08-27'), '/api/tasks/1');
        $task->setAfter(new \DateTime('2026-08-27 22:00:00'));
        $task->setBefore(new \DateTime('2026-08-28 02:00:00'));

        foreach (['2026-08-27', '2026-08-28'] as $day) {
            $taskList = $this->taskListWith($courier, new \DateTime($day), []);

            $this->taskListManager->assign($taskList, ['/api/tasks/1']);

            $this->assertTrue($taskList->containsTask($task), sprintf('Task should be assignable on %s', $day));
        }
    }
}
