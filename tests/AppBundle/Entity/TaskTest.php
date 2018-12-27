<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\TaskEvent;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    private $task;
    private $courier;

    protected function setUp()
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

    private function createTaskEvent($name, \DateTime $createdAt)
    {
        $taskEvent = new TaskEvent($this->task, $name);

        $reflection = new \ReflectionObject($taskEvent);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($taskEvent, $createdAt);

        return $taskEvent;
    }

    public function testGetLastEvent()
    {
        $event1 = $this->createTaskEvent('task:assigned', new \DateTime('2018-04-11 12:00:00'));
        $event2 = $this->createTaskEvent('task:unassigned', new \DateTime('2018-04-11 13:00:00'));
        $event3 = $this->createTaskEvent('task:assigned', new \DateTime('2018-04-11 14:00:00'));
        $event4 = $this->createTaskEvent('task:unassigned', new \DateTime('2018-04-11 15:00:00'));

        $this->task->getEvents()->add($event1);
        $this->task->getEvents()->add($event2);
        $this->task->getEvents()->add($event3);
        $this->task->getEvents()->add($event4);

        $this->assertSame($this->task->getLastEvent('task:assigned'), $event3);
    }
}
