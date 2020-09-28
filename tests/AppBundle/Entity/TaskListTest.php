<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;

class TaskListTest extends TaskCollectionTest
{
    public function setUp(): void
    {
        $user = new User();

        $this->taskCollection = new TaskList();
        $this->taskCollection->setCourier($user);
    }


    public function testAddRemoveTaskChangesAssignedUser()
    {
        $user = new User();

        $taskList = new TaskList();
        $taskList->setCourier($user);
        $taskList->setDate(new \DateTime());

        $task = new Task();

        $taskList->addTask($task);
        $this->assertSame($user, $task->getAssignedCourier());

        $taskList->removeTask($task);
        $this->assertNull($task->getAssignedCourier());
    }
}
