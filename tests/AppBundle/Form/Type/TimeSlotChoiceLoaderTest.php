<?php

namespace Tests\AppBundle\Form\Type;

use AppBundle\Entity\TimeSlot;
use AppBundle\Entity\TimeSlot\Choice;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TimeSlotChoiceLoaderTest extends TestCase
{
    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    private function assertTimeSlotChoice(\DateTime $start, \DateTime $end, TimeSlotChoice $choice)
    {
        $datePeriod = $choice->toDatePeriod();

        $this->assertEquals($start, $datePeriod->start);
        $this->assertEquals($end, $datePeriod->end);
    }

    public function testGetChoicesWithDatesOnSunday()
    {
        $choice1 = new Choice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Sunday
        Carbon::setTestNow(Carbon::parse('2019-07-28 19:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-07-29 11:00:00'),
            new \DateTime('2019-07-29 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-07-30 11:00:00'),
            new \DateTime('2019-07-30 12:00:00'),
            $choices[1]
        );
    }

    public function testGetChoicesWithDatesOnMonday()
    {
        $choice1 = new Choice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Monday
        Carbon::setTestNow(Carbon::parse('2019-07-29 08:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-07-29 11:00:00'),
            new \DateTime('2019-07-29 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-07-30 11:00:00'),
            new \DateTime('2019-07-30 12:00:00'),
            $choices[1]
        );
    }

    public function testGetChoicesWithDatesOnMondayTooLate()
    {
        $choice1 = new Choice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Monday
        Carbon::setTestNow(Carbon::parse('2019-07-29 13:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-07-30 11:00:00'),
            new \DateTime('2019-07-30 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-07-31 11:00:00'),
            new \DateTime('2019-07-31 12:00:00'),
            $choices[1]
        );
    }

    public function testGetChoicesWithDatesOnDayBeforeBankHoliday()
    {
        $choice1 = new Choice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->addChoice($choice1);

        // Day befoe bank holiday
        Carbon::setTestNow(Carbon::parse('2019-08-14 13:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-08-16 11:00:00'),
            new \DateTime('2019-08-16 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-08-19 11:00:00'),
            new \DateTime('2019-08-19 12:00:00'),
            $choices[1]
        );
    }

    public function testGetChoicesWithDatesOnFridayNotOnlyWorkingDays()
    {
        $choice1 = new Choice();
        $choice1->setStartTime('11:00:00');
        $choice1->setEndTime('12:00:00');

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->addChoice($choice1);

        // Friday
        Carbon::setTestNow(Carbon::parse('2019-10-25 19:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-26 11:00:00'),
            new \DateTime('2019-10-26 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-27 11:00:00'),
            new \DateTime('2019-10-27 12:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHours()
    {
        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00']);

        // Friday
        Carbon::setTestNow(Carbon::parse('2019-10-25 19:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-28 10:00:00'),
            new \DateTime('2019-10-28 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-29 10:00:00'),
            new \DateTime('2019-10-29 12:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHoursWithPriorNotice()
    {
        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00']);
        $slot->setPriorNotice('2 hours');
        $slot->setSameDayCutoff('');

        // Monday, too late
        Carbon::setTestNow(Carbon::parse('2019-10-21 09:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-21 10:00:00'),
            new \DateTime('2019-10-21 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-22 10:00:00'),
            new \DateTime('2019-10-22 12:00:00'),
            $choices[1]
        );

        // Monday, on time
        Carbon::setTestNow(Carbon::parse('2019-10-21 07:30:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-21 10:00:00'),
            new \DateTime('2019-10-21 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-22 10:00:00'),
            new \DateTime('2019-10-22 12:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHoursWithSameDayCutoffExpired()
    {
        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours([
            'Mo-Fr 10:00-11:00',
            'Mo-Fr 11:00-12:00',
            'Mo-Fr 12:00-14:00',
            'Mo-Fr 14:00-16:00'
        ]);
        $slot->setSameDayCutoff('11:00');

        Carbon::setTestNow(Carbon::parse('2019-10-21 11:30:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(8, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-22 10:00:00'),
            new \DateTime('2019-10-22 11:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-22 11:00:00'),
            new \DateTime('2019-10-22 12:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHoursWithSameDayCutoffNotExpired()
    {
        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours([
            'Mo-Fr 10:00-11:00',
            'Mo-Fr 11:00-12:00',
            'Mo-Fr 12:00-14:00',
            'Mo-Fr 14:00-16:00'
        ]);
        $slot->setSameDayCutoff('11:00');

        Carbon::setTestNow(Carbon::parse('2019-10-21 09:30:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(8, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-21 10:00:00'),
            new \DateTime('2019-10-21 11:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-21 11:00:00'),
            new \DateTime('2019-10-21 12:00:00'),
            $choices[1]
        );
    }
}
