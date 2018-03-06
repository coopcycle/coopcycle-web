<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\ApiUser;
use PHPUnit\Framework\TestCase;

abstract class TaskCollectionTest extends TestCase
{
    protected $taskCollection;

    private function assertAddTask(Task $task)
    {
        $this->assertCount(1, $this->taskCollection->getItems());
        $this->assertInstanceOf(TaskCollectionItem::class, $this->taskCollection->getItems()->get(0));
        $this->assertSame($task, $this->taskCollection->getItems()->get(0)->getTask());
        $this->assertSame($this->taskCollection, $this->taskCollection->getItems()->get(0)->getParent());
    }

    public function testAddTaskWithoutPosition()
    {
        $task = new Task();

        $this->taskCollection->addTask($task);

        $this->assertAddTask($task);
        $this->assertEquals(-1, $this->taskCollection->getItems()->get(0)->getPosition());
    }

    public function testAddTaskWithPosition()
    {
        $task = new Task();

        $this->taskCollection->addTask($task, 4);

        $this->assertAddTask($task);
        $this->assertEquals(4, $this->taskCollection->getItems()->get(0)->getPosition());
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
}
