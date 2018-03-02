<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\TaskEvent;
use PHPUnit\Framework\TestCase;



class TaskTest extends TestCase
{

    private $task;
    private $courier;

    public function setUp()
    {
        $this->task = new Task();
        $this->courier = new ApiUser();
    }

    public function testSetPrevious()
    {
        $previoustask = new Task();
        $this->task->setPrevious($previoustask);
        $this->assertSame($previoustask, $this->task->getPrevious());
    }

    public function testHasPrevious()
    {
        $this->assertFalse($this->task->hasPrevious());
        $previoustask = new Task();
        $this->task->setPrevious($previoustask);
        $this->assertTrue($this->task->hasPrevious());
    }

    public function testAssignTo()
    {
        $this->task->assignTo($this->courier);
        $this->assertTrue($this->task->isAssigned());
        $this->assertTrue($this->task->isAssignedTo($this->courier));
        $this->assertEquals($this->task->getAssignedCourier(), $this->courier);
    }

    public function testUnassign()
    {
        $this->task->assignTo($this->courier);
        $this->task->unassign();
        $this->assertNull($this->task->getAssignedCourier());
    }

    public function testHasEvent()
    {
        $event = new TaskEvent($this->task, "PICKUP");
        $this->task->getEvents()->add($event);
        $this->assertTrue($this->task->hasEvent("PICKUP"));
        $this->assertFalse($this->task->hasEvent("DROPOFF"));
    }

    public function testGetFirstEvent()
    {
        $first_event = new TaskEvent($this->task, "PICKUP");
        $this->task->getEvents()->add($first_event);
        $second_event = new TaskEvent($this->task, "DELIVERY");
        $this->task->getEvents()->add($second_event);
        $third_event = new TaskEvent($this->task, "PICKUP");
        $this->task->getEvents()->add($first_event);
        $this->assertSame($this->task->getFirstEvent("PICKUP"),
                           $first_event);
    }
}
