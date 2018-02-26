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

    public function testIsAssigned()
    {
        $this->assertFalse($this->task->isAssigned());
        $this->task->assignTo($this->courier, 1);
        $this->assertTrue($this->task->isAssigned());
    }

    public function testIsAssignedTo()
    {
        $this->task->assignTo($this->courier, 1);
        $this->assertTrue($this->task->isAssignedTo($this->courier));
    }

    public function testGetAssignedCourier()
    {
        $this->task->assignTo($this->courier, 1);
        $this->assertEquals($this->task->getAssignedCourier(),
                            $this->courier);
    }

    public function testAssignedTo()
    {
        $this->task->assignTo($this->courier, 1);
        $this->assertEquals($this->task->getAssignment()->getCourier(), $this->courier);
        $this->assertEquals($this->task->getAssignment()->getPosition(), 1);
    }

    public function testUnassign()
    {
        $this->task->assignTo($this->courier, 1);
        $this->task->unassign();
        $this->assertNull($this->task->getAssignment());
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
