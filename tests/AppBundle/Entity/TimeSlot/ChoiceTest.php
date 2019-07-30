<?php

namespace Tests\AppBundle\Entity\TimeSlot;

use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot\Choice as TimeSlotChoice;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ChoiceTest extends TestCase
{
    public function tearDown()
    {
        Carbon::setTestNow();
    }

    public function testToDateTime()
    {
        $choice = new TimeSlotChoice();
        $choice->setStartTime('11:00:00');
        $choice->setEndTime('12:00:00');

        Carbon::setTestNow(Carbon::parse('2019-07-30'));
        $dates = $choice->toDateTime();

        $this->assertThat($dates, $this->isType('array'));
        $this->assertCount(2, $dates);
        $this->assertEquals(new \DateTime('2019-07-30 11:00:00'), $dates[0]);
        $this->assertEquals(new \DateTime('2019-07-30 12:00:00'), $dates[1]);

        $dates = $choice->toDateTime(new \DateTime('2019-07-29'));

        $this->assertThat($dates, $this->isType('array'));
        $this->assertCount(2, $dates);
        $this->assertEquals(new \DateTime('2019-07-29 11:00:00'), $dates[0]);
        $this->assertEquals(new \DateTime('2019-07-29 12:00:00'), $dates[1]);
    }

    public function testApply()
    {
        $choice = new TimeSlotChoice();
        $choice->setStartTime('11:00:00');
        $choice->setEndTime('12:00:00');

        $task = new Task();

        Carbon::setTestNow(Carbon::parse('2019-07-30'));
        $dates = $choice->apply($task);

        $this->assertEquals(new \DateTime('2019-07-30 11:00:00'), $task->getDoneAfter());
        $this->assertEquals(new \DateTime('2019-07-30 12:00:00'), $task->getDoneBefore());

        $dates = $choice->apply($task, new \DateTime('2019-07-29'));

        $this->assertEquals(new \DateTime('2019-07-29 11:00:00'), $task->getDoneAfter());
        $this->assertEquals(new \DateTime('2019-07-29 12:00:00'), $task->getDoneBefore());
    }
}
