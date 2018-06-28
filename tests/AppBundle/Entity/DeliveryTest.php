<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use PHPUnit\Framework\TestCase;

class DeliveryTest extends TestCase
{
    public function testNewDeliveryHasTwoTasks()
    {
        $delivery = new Delivery();

        $this->assertNotNull($delivery->getPickup());
        $this->assertNotNull($delivery->getDropoff());
        $this->assertCount(2, $delivery->getTasks());
    }

    public function testAddTaskThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        $delivery = new Delivery();

        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);

        $delivery->addTask($task);
    }
}
