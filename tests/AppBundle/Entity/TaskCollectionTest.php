<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\Tour;
use PHPUnit\Framework\TestCase;

class TaskCollectionTest extends TestCase
{
    protected $taskCollection;

    public function setUp(): void
    {
        $this->taskCollection = new Tour();
    }

    private function assertAddTask($count)
    {
        $this->assertCount($count, $this->taskCollection->getItems());
        $this->assertInstanceOf(TaskCollectionItem::class, $this->taskCollection->getItems()->get(0));
        $this->assertSame($this->taskCollection, $this->taskCollection->getItems()->get(0)->getParent());
    }

    public function testAddTasksWithoutPosition()
    {
        $task = new Task();
        $task2 = new Task();

        $this->taskCollection->addTask($task);

        $this->assertAddTask(1);

        $this->assertEquals($task, $this->taskCollection->findAt(0)->getTask());

        $this->taskCollection->addTask($task2);

        $this->assertAddTask(2);
        $this->assertEquals($task2, $this->taskCollection->findAt(1)->getTask());
    }

    public function testAddSameTaskTwiceWithoutPosition()
    {
        $task = new Task();

        $this->taskCollection->addTask($task);

        $this->assertAddTask(1);
        $this->assertEquals(0, $this->taskCollection->getItems()->get(0)->getPosition());

        $this->taskCollection->addTask($task);

        $this->assertAddTask(1);
        $this->assertEquals(0, $this->taskCollection->getItems()->get(0)->getPosition());
        $this->assertEquals($task, $this->taskCollection->findAt(0)->getTask());
    }

    public function testAddTaskWithPosition()
    {
        $task = new Task();

        $this->taskCollection->addTask($task, 4);

        $this->assertAddTask(1);
        $this->assertEquals($task, $this->taskCollection->findAt(4)->getTask());
    }

    public function testAddTaskUpdatesPosition()
    {
        $task = new Task();

        $this->taskCollection->addTask($task, 4);

        $this->assertCount(1, $this->taskCollection->getItems());
        $this->assertEquals(4, $this->taskCollection->getItems()->get(0)->getPosition());

        $this->taskCollection->addTask($task, 5);

        $this->assertCount(1, $this->taskCollection->getItems());
        $this->assertEquals(5, $this->taskCollection->getItems()->get(0)->getPosition());
    }

    public function testContainsTask()
    {
        $task1 = new Task();
        $task2 = new Task();

        $this->taskCollection->addTask($task1);

        $this->assertTrue($this->taskCollection->containsTask($task1));
        $this->assertFalse($this->taskCollection->containsTask($task2));

        $this->taskCollection->removeTask($task1);

        $this->assertFalse($this->taskCollection->containsTask($task1));
    }

    public function testSetTasksInsertTask()
    {
        $task = new Task();
        $task1 = new Task();
        $newTask = new Task();

        $this->taskCollection->addTask($task, 0);
        $this->taskCollection->addTask($task1, 1);

        $tasksToAssign = [
            0 => $task,
            1 => $newTask,
            2 => $task1,
        ];
        $this->taskCollection->setTasks($tasksToAssign);

        $this->assertEquals($this->taskCollection->findAt(0)->getTask(), $task);
        $this->assertEquals($this->taskCollection->findAt(1)->getTask(), $newTask);
        $this->assertEquals($this->taskCollection->findAt(2)->getTask(), $task);
    }

    public function testSetTasksPushTask()
    {
        $task = new Task();
        $task1 = new Task();
        $newTask = new Task();

        $this->taskCollection->addTask($task, 0);
        $this->taskCollection->addTask($task1, 1);

        $tasksToAssign = [
            0 => $task,
            1 => $task1,
            2 => $newTask
        ];
        $this->taskCollection->setTasks($tasksToAssign);

        $this->assertEquals($this->taskCollection->findAt(0)->getTask(), $task);
        $this->assertEquals($this->taskCollection->findAt(1)->getTask(), $task1);
        $this->assertEquals($this->taskCollection->findAt(2)->getTask(), $newTask);
    }

    public function testSetTasksRemoveTask()
    {
        $task = new Task();
        $task1 = new Task();
        $toRemove = new Task();

        $this->taskCollection->addTask($task, 0);
        $this->taskCollection->addTask($task1, 1);
        $this->taskCollection->addTask($toRemove, 2);

        $tasksToAssign = [
            0 => $task,
            1 => $task1
        ];
        $this->taskCollection->setTasks($tasksToAssign);

        $this->assertSame($task, $this->taskCollection->findAt(0)->getTask());
        $this->assertSame($task1, $this->taskCollection->findAt(1)->getTask());
        $this->assertNull($this->taskCollection->findAt(2));

    }

    public function testSetTasksRemoveTaskNotLast()
    {
        $task = new Task();
        $task1 = new Task();
        $toRemove = new Task();

        $this->taskCollection->addTask($task, 0);
        $this->taskCollection->addTask($toRemove, 1);
        $this->taskCollection->addTask($task1, 2);

        $tasksToAssign = [
            0 => $task,
            1 => $task1,
        ];
        $this->taskCollection->setTasks($tasksToAssign);

        $this->assertSame($task, $this->taskCollection->findAt(0)->getTask());
        $this->assertSame($task1, $this->taskCollection->findAt(1)->getTask());
        $this->assertNull($this->taskCollection->findAt(2));

    }

    public function testUpdateTasksReorderTasks()
    {
        $task = new Task();
        $task1 = new Task();
        $task2 = new Task();

        $this->taskCollection->addTask($task, 0);
        $this->taskCollection->addTask($task1, 1);
        $this->taskCollection->addTask($task2, 2);

        $tasksToAssign = [
            0 => $task,
            1 => $task2,
            2 => $task1,
        ];
        $this->taskCollection->setTasks($tasksToAssign);

        $this->assertSame($task, $this->taskCollection->findAt(0)->getTask());
        $this->assertSame($task2, $this->taskCollection->findAt(1)->getTask());
        $this->assertSame($task1, $this->taskCollection->findAt(2)->getTask());

    }
}
