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
use Prophecy\PhpUnit\ProphecyTrait;

class TaskListManagerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $iriConverter;
    private $taskListManager;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->iriConverter = $this->prophesize(IriConverterInterface::class);

        $this->taskListManager = new TaskListManager(
            $this->entityManager->reveal(),
            $this->iriConverter->reveal()
        );
    }

    /**
     * Helper: build a task assigned to $courier on $date, with a given status.
     */
    private function assignedTask(User $courier, \DateTime $date, string $iri, string $status = Task::STATUS_TODO): Task
    {
        $task = new Task();
        $task->setStatus($status);
        $task->assignTo($courier, $date);

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
}
