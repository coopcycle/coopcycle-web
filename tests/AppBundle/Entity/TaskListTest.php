<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\ApiUser;

class TaskListTest extends TaskCollectionTest
{
    public function setUp()
    {
        $user = new ApiUser();

        $this->taskCollection = new TaskList();
        $this->taskCollection->setCourier($user);
    }


    public function testAddRemoveTaskChangesAssignedUser()
    {
        $user = new ApiUser();

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
