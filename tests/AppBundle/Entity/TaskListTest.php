<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class TaskListTest extends TestCase
{
    protected $taskCollection;

    public function setUp(): void
    {
        $user = new User();

        $this->taskCollection = new TaskList();
        $this->taskCollection->setCourier($user);
    }

    // TODO : this should pass once we setup the listener
    public function testAddRemoveTaskChangesAssignedUser()
    {
        // $user = new User();

        // $taskList = new TaskList();
        // $taskList->setCourier($user);
        // $taskList->setDate(new \DateTime());

        // $task = new Task();

        // $taskList->addTask($task);
        // $this->assertSame($user, $task->getAssignedCourier());

        // $taskList->removeTask($task);
        // $this->assertNull($task->getAssignedCourier());
    }
}
