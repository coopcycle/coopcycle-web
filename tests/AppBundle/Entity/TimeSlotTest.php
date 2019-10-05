<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\TimeSlot;
use AppBundle\Entity\TimeSlot\Choice as TimeSlotChoice;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TimeSlotTest extends TestCase
{
    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testGetChoicesWithDatesOnSunday()
    {
        $choice1 = new TimeSlotChoice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Sunday
        Carbon::setTestNow(Carbon::parse('2019-07-28 19:00:00'));

        $choicesWithDates = $slot->getChoicesWithDates('fr');

        $this->assertCount(2, $choicesWithDates);

        [ $choicesWithDate1, $choicesWithDate2 ] = $choicesWithDates;

        $this->assertTimeSlotChoices(
            new \DateTime('2019-07-29 11:00:00'),
            new \DateTime('2019-07-29 12:00:00'),
            $choicesWithDate1
        );
        $this->assertTimeSlotChoices(
            new \DateTime('2019-07-30 11:00:00'),
            new \DateTime('2019-07-30 12:00:00'),
            $choicesWithDate2
        );
    }

    public function testGetChoicesWithDatesOnMonday()
    {
        $choice1 = new TimeSlotChoice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Monday
        Carbon::setTestNow(Carbon::parse('2019-07-29 08:00:00'));

        $choicesWithDates = $slot->getChoicesWithDates('fr');

        $this->assertCount(2, $choicesWithDates);

        [ $choicesWithDate1, $choicesWithDate2 ] = $choicesWithDates;

        $this->assertTimeSlotChoices(
            new \DateTime('2019-07-29 11:00:00'),
            new \DateTime('2019-07-29 12:00:00'),
            $choicesWithDate1
        );
        $this->assertTimeSlotChoices(
            new \DateTime('2019-07-30 11:00:00'),
            new \DateTime('2019-07-30 12:00:00'),
            $choicesWithDate2
        );
    }

    public function testGetChoicesWithDatesOnMondayTooLate()
    {
        $choice1 = new TimeSlotChoice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Monday
        Carbon::setTestNow(Carbon::parse('2019-07-29 13:00:00'));

        $choicesWithDates = $slot->getChoicesWithDates('fr');

        $this->assertCount(2, $choicesWithDates);

        [ $choicesWithDate1, $choicesWithDate2 ] = $choicesWithDates;

        $this->assertTimeSlotChoices(
            new \DateTime('2019-07-30 11:00:00'),
            new \DateTime('2019-07-30 12:00:00'),
            $choicesWithDate1
        );
        $this->assertTimeSlotChoices(
            new \DateTime('2019-07-31 11:00:00'),
            new \DateTime('2019-07-31 12:00:00'),
            $choicesWithDate2
        );
    }

    public function testGetChoicesWithDatesOnDayBeforeBankHoliday()
    {
        $choice1 = new TimeSlotChoice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Day befoe bank holiday
        Carbon::setTestNow(Carbon::parse('2019-08-14 13:00:00'));

        $choicesWithDates = $slot->getChoicesWithDates('fr');

        $this->assertCount(2, $choicesWithDates);

        [ $choicesWithDate1, $choicesWithDate2 ] = $choicesWithDates;

        $this->assertTimeSlotChoices(
            new \DateTime('2019-08-16 11:00:00'),
            new \DateTime('2019-08-16 12:00:00'),
            $choicesWithDate1
        );
        $this->assertTimeSlotChoices(
            new \DateTime('2019-08-19 11:00:00'),
            new \DateTime('2019-08-19 12:00:00'),
            $choicesWithDate2
        );
    }

    private function assertTimeSlotChoices(\DateTime $start, \DateTime $end, TimeSlotChoiceWithDate $timeSlotChoice)
    {
        [ $choiceWithDateStart, $choiceWithDateEnd ] = $timeSlotChoice->toDateTime();

        $this->assertEquals($start, $choiceWithDateStart);
        $this->assertEquals($end, $choiceWithDateEnd);
    }
}
