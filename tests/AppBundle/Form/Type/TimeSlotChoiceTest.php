<?php

namespace Tests\AppBundle\Form\Type;

use AppBundle\Entity\Task;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TimeSlotChoiceTest extends TestCase
{
    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testToDatePeriod()
    {
        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-12:00:00');

        $datePeriod = $choice->toDatePeriod();

        $this->assertEquals(new \DateTime('2019-07-30 11:00:00'), $datePeriod->start);
        $this->assertEquals(new \DateTime('2019-07-30 12:00:00'), $datePeriod->end);
    }

    public function testHasBegun()
    {
        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-12:00:00');

        $this->assertFalse($choice->hasBegun(new \DateTime('2019-07-30 09:00:00')));
        $this->assertTrue($choice->hasBegun(new \DateTime('2019-07-30 11:00:00')));
        $this->assertTrue($choice->hasBegun(new \DateTime('2019-07-30 11:05:00')));
        $this->assertTrue($choice->hasBegun(new \DateTime('2019-07-30 13:05:00')));
    }

    public function testHasBegunWithPriorNotice()
    {
        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-12:00:00');

        $this->assertTrue($choice->hasBegun(new \DateTime('2019-07-30 09:00:00'), '3 hours'));
        $this->assertTrue($choice->hasBegun(new \DateTime('2019-07-30 09:00:00'), '2 hours'));
        $this->assertFalse($choice->hasBegun(new \DateTime('2019-07-30 09:00:00'), '1 hour'));
    }

    public function testHasFinished()
    {
        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-14:00:00');

        $this->assertFalse($choice->hasFinished(new \DateTime('2019-07-30 09:00:00')));
        $this->assertFalse($choice->hasFinished(new \DateTime('2019-07-30 11:00:00')));
        $this->assertFalse($choice->hasFinished(new \DateTime('2019-07-30 11:05:00')));
        $this->assertTrue($choice->hasFinished(new \DateTime('2019-07-30 14:05:00')));
    }

    public function testHasFinishedWithPriorNotice()
    {
        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-14:00:00');

        // 11:00 max
        $this->assertFalse($choice->hasFinished(new \DateTime('2019-07-30 09:00:00'), '3 hours'));
        $this->assertTrue($choice->hasFinished(new \DateTime('2019-07-30 12:30:00'), '3 hours'));
        // 12:00 max
        $this->assertFalse($choice->hasFinished(new \DateTime('2019-07-30 09:00:00'), '2 hours'));
        $this->assertTrue($choice->hasFinished(new \DateTime('2019-07-30 13:00:00'), '2 hours'));
        // 12:00 max
        $this->assertFalse($choice->hasFinished(new \DateTime('2019-07-30 09:00:00'), '1 hour'));
    }

    public function testApplyToTask()
    {
        $task = new Task();

        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-12:00:00');
        $choice->applyToTask($task);

        $this->assertEquals(new \DateTime('2019-07-30 11:00:00'), $task->getDoneAfter());
        $this->assertEquals(new \DateTime('2019-07-30 12:00:00'), $task->getDoneBefore());
    }

    public function testToString()
    {
        $choice = new TimeSlotChoice(new \DateTime('2019-07-30'), '11:00:00-12:00:00');

        $this->assertEquals('2019-07-30 11:00-12:00', (string) $choice);
    }
}
