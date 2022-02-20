<?php

namespace Tests\AppBundle\Form\Type;

use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Utils\TimeSlotChoiceWithDate;
use Doctrine\Common\Collections\ArrayCollection;
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
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Su 11:00-12:00']);

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
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Su 11:00-12:00']);

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
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Su 11:00-12:00']);

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
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Su 11:00-12:00']);

        // Day before bank holiday
        Carbon::setTestNow(Carbon::parse('2019-08-14 13:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', null, new \DateTime('2019-08-19 19:00:00'));
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
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Su 11:00-12:00']);
        $slot->setWorkingDaysOnly(false);

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
        // Friday
        Carbon::setTestNow(Carbon::parse('2019-10-25 19:00:00'));

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00']);

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', null, new \DateTime('2019-10-29 19:00:00'));
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
        Carbon::setTestNow(Carbon::parse('2019-10-21 11:30:00'));

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours([
            'Mo-Fr 10:00-11:00',
            'Mo-Fr 11:00-12:00',
            'Mo-Fr 12:00-14:00',
            'Mo-Fr 14:00-16:00'
        ]);
        $slot->setSameDayCutoff('11:00');

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', null, new \DateTime('2019-10-23 17:00:00'));
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
        $this->assertTimeSlotChoice(
            new \DateTime('2019-10-23 14:00:00'),
            new \DateTime('2019-10-23 16:00:00'),
            $choices[7]
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

    public function testWithOpeningHoursAndClosingRules()
    {
        // Friday
        Carbon::setTestNow(Carbon::parse('2020-12-23 09:00:00'));

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00']);

        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2020-12-24 10:00:00'));
        $closingRule->setEndDate(new \DateTime('2020-12-28 10:00:00'));

        $closingRules->add($closingRule);

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', $closingRules, new \DateTime('2020-12-28 19:00:00'));
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2020-12-23 10:00:00'),
            new \DateTime('2020-12-23 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2020-12-28 10:00:00'),
            new \DateTime('2020-12-28 12:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHoursAndPastClosingRules()
    {
        // Friday
        Carbon::setTestNow(Carbon::parse('2020-12-23 19:00:00'));

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00']);

        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2019-12-24 10:00:00'));
        $closingRule->setEndDate(new \DateTime('2019-12-28 10:00:00'));

        $closingRules->add($closingRule);

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', $closingRules);
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2020-12-24 10:00:00'),
            new \DateTime('2020-12-24 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2020-12-25 10:00:00'),
            new \DateTime('2020-12-25 12:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHoursWithMaxDate()
    {
        // Thursday

        $now = Carbon::parse('2020-12-22 09:00:00');

        Carbon::setTestNow($now);

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00', 'Mo-Fr 12:00-14:00']);
        $slot->setInterval('1 month');

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', null, $now->copy()->add(1, 'day'));
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2020-12-22 10:00:00'),
            new \DateTime('2020-12-22 12:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2020-12-22 12:00:00'),
            new \DateTime('2020-12-22 14:00:00'),
            $choices[1]
        );
    }

    public function testWithOpeningHoursWithClosingRules()
    {
        // Thursday

        $now = Carbon::parse('2020-12-24 09:00:00');

        Carbon::setTestNow($now);

        $slot = new TimeSlot();
        $slot->setWorkingDaysOnly(false);
        $slot->setOpeningHours(['Mo-Fr 10:00-12:00', 'Mo-Fr 12:00-14:00']);

        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2020-12-23 09:00:00'));
        $closingRule->setEndDate(new \DateTime('2021-01-05 10:00:00'));

        $closingRules->add($closingRule);

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr', $closingRules, $now->copy()->add(7, 'days'));
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(0, $choices);
    }

    public function testGetChoicesWithCutoff()
    {
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Su 09:00-17:00']);
        $slot->setSameDayCutoff('10:00');

        // Sunday
        Carbon::setTestNow(Carbon::parse('2021-07-20 11:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2021-07-21 09:00:00'),
            new \DateTime('2021-07-21 17:00:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2021-07-22 09:00:00'),
            new \DateTime('2021-07-22 17:00:00'),
            $choices[1]
        );
    }

    public function testGetChoicesWithEmptyDays()
    {
        $slot = new TimeSlot();
        $slot->setOpeningHours(['Mo-Sa 09:00-10:30', '12:00-14:00']);

        // Sunday
        Carbon::setTestNow(Carbon::parse('2021-07-20 11:00:00'));

        $choiceLoader = new TimeSlotChoiceLoader($slot, 'fr');
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);

        $this->assertTimeSlotChoice(
            new \DateTime('2021-07-21 09:00:00'),
            new \DateTime('2021-07-21 10:30:00'),
            $choices[0]
        );
        $this->assertTimeSlotChoice(
            new \DateTime('2021-07-22 09:00:00'),
            new \DateTime('2021-07-22 10:30:00'),
            $choices[1]
        );
    }
}
