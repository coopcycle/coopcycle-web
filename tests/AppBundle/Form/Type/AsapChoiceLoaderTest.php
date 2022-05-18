<?php

namespace Tests\AppBundle\Form\Type;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Restaurant;
use AppBundle\Form\Type\AsapChoiceLoader;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TsRangeChoice;
use AppBundle\Service\TimeRegistry;
use AppBundle\Utils\DateUtils;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class AsapChoiceLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->timeRegistry = $this->prophesize(TimeRegistry::class);
        $this->timeRegistry->getAveragePreparationTime()->willReturn(0);
        $this->timeRegistry->getAverageShippingTime()->willReturn(0);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    private function rangeToString(TsRange $range)
    {
        return $range->getLower()->format('Y-m-d H:i').' - '.$range->getUpper()->format('Y-m-d H:i');
    }

    private function toTimeRanges($choices)
    {
        return array_map(fn(TsRangeChoice $choice) => $choice->toTsRange(), $choices);
    }

    private function assertContainsDate($date, array $dates)
    {
        $this->assertContains(is_string($date) ? $date : $date->format(\DateTime::ATOM), $dates);
    }

    private function assertContainsTimeRange(TsRange $range, array $dates)
    {
        $toString = array_map(function (TsRange $range) {
            return $this->rangeToString($range);
        }, $dates);

        $this->assertContains($this->rangeToString($range), $toString);
    }

    private function assertNotContainsDate($date, array $dates)
    {
        $this->assertNotContains(is_string($date) ? $date : $date->format(\DateTime::ATOM), $dates);
    }

    private function assertContainsDates(array $expected, array $choices)
    {
        $expected = array_map(function ($date) {
            return DateUtils::dateTimeToTsRange(new \DateTime($date), 5);
        }, $expected);

        foreach ($expected as $value) {
            $this->assertContainsTimeRange($value, $this->toTimeRanges($choices));
        }
    }

    private function assertNotContainsDates(array $expected, array $dates)
    {
        foreach ($expected as $date) {
            $this->assertNotContainsDate($date, $dates);
        }
    }

    private function assertNotContainsTimeRanges(array $expected, array $choices)
    {
        $strings = array_map(function ($date) {
            $range = new TsRange();
            $range->setLower(new \DateTime($date[0]));
            $range->setUpper(new \DateTime($date[1]));

            return $this->rangeToString($range);
        }, $expected);

        $choicesAsString = array_map(function (TsRange $range) {
            return $this->rangeToString($range);
        }, $this->toTimeRanges($choices));

        foreach ($strings as $string) {
            $this->assertNotContains($string, $choicesAsString);
        }
    }

    private function assertContainsDays($expected, array $dates)
    {
        $days = array_reduce($dates, function ($days, TsRangeChoice $item) {
            $day = $item->toTsRange()->getLower()->format('Y-m-d');
            if (!in_array($day, $days)) {
                $days[] = $day;
            }

            return $days;
        }, []);

        $this->assertEquals($expected, $days);
    }

    private function assertContainsTimeRanges(array $expected, array $choices)
    {
        $expected = array_map(function ($date) {
            $range = new TsRange();
            $range->setLower(new \DateTime($date[0]));
            $range->setUpper(new \DateTime($date[1]));

            return $range;
        }, $expected);

        foreach ($expected as $value) {
            $this->assertContainsTimeRange($value, $this->toTimeRanges($choices));
        }
    }

    private function assertNotContainsDay($expected, array $dates)
    {
        $days = array_reduce($dates, function ($days, TsRangeChoice $item) {
            $day = $item->toTsRange()->getLower()->format('Y-m-d');
            if (!in_array($day, $days)) {
                $days[] = $day;
            }

            return $days;
        }, []);

        $this->assertNotContains($expected, $days);
    }

    public function test247()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Su 00:00-23:59"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2017-10-04T17:30:00+02:00', '2017-10-04T17:40:00+02:00'],
            ['2017-10-04T23:50:00+02:00', '2017-10-05T00:00:00+02:00'],
            ['2017-10-05T23:50:00+02:00', '2017-10-06T00:00:00+02:00'],
            ['2017-10-06T23:50:00+02:00', '2017-10-07T00:00:00+02:00'],
            ['2017-10-11T17:20:00+02:00', '2017-10-11T17:30:00+02:00'],
        ], $choices);

        $this->assertNotContainsTimeRanges([
            ['2017-10-11T23:50:00+02:00', '2017-10-12T00:00:00+02:00'],
        ], $choices);

        $this->assertContainsDays([
            "2017-10-04",
            "2017-10-05",
            "2017-10-06",
            "2017-10-07",
            "2017-10-08",
            "2017-10-09",
            "2017-10-10",
            "2017-10-11",
        ], $choices);
    }

    public function testSameDay()
    {
        // 2017-10-04 is a Wednesday
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2017-10-04T17:30:00+02:00', '2017-10-04T17:40:00+02:00' ],
            [ '2017-10-04T18:00:00+02:00', '2017-10-04T18:10:00+02:00' ],
            [ '2017-10-04T18:40:00+02:00', '2017-10-04T18:50:00+02:00' ],
            [ '2017-10-04T18:50:00+02:00', '2017-10-04T19:00:00+02:00' ],
            [ '2017-10-05T10:00:00+02:00', '2017-10-05T10:10:00+02:00' ],
        ], $choices);

        $this->assertContainsDays([
            "2017-10-04",
            "2017-10-05",
            "2017-10-06",
            "2017-10-07",
            // Sunday
            "2017-10-09",
            "2017-10-10",
            "2017-10-11",
        ], $choices);
    }

    public function testSameDayWithOneShippingOption()
    {
        Carbon::setTestNowAndTimezone(Carbon::parse('2020-03-12 15:30:00'));

        $openingHours = [
            "Tu-Su 13:15-16:00",
            "Tu-Su 20:15-23:15"
        ];

        $choiceLoader = new AsapChoiceLoader($openingHours, $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-03-12T15:30:00+02:00', '2020-03-12T15:40:00+02:00'],
            ['2020-03-12T15:40:00+02:00', '2020-03-12T15:50:00+02:00'],
            ['2020-03-12T20:20:00+02:00', '2020-03-12T20:30:00+02:00'],
            ['2020-03-12T20:30:00+02:00', '2020-03-12T20:40:00+02:00'],
            ['2020-03-12T20:40:00+02:00', '2020-03-12T20:50:00+02:00'],
            ['2020-03-12T20:50:00+02:00', '2020-03-12T21:00:00+02:00'],
            ['2020-03-12T21:00:00+02:00', '2020-03-12T21:10:00+02:00'],
            ['2020-03-12T21:10:00+02:00', '2020-03-12T21:20:00+02:00'],
            ['2020-03-12T21:20:00+02:00', '2020-03-12T21:30:00+02:00'],
            ['2020-03-12T21:30:00+02:00', '2020-03-12T21:40:00+02:00'],
            ['2020-03-12T21:40:00+02:00', '2020-03-12T21:50:00+02:00'],
            ['2020-03-12T22:10:00+02:00', '2020-03-12T22:20:00+02:00'],
            ['2020-03-12T22:20:00+02:00', '2020-03-12T22:30:00+02:00'],
            ['2020-03-12T22:40:00+02:00', '2020-03-12T22:50:00+02:00'],
            ['2020-03-12T22:50:00+02:00', '2020-03-12T23:00:00+02:00'],
            ['2020-03-12T23:00:00+02:00', '2020-03-12T23:10:00+02:00'],
            ['2020-03-12T23:10:00+02:00', '2020-03-12T23:20:00+02:00'],
        ], $choices);

        $this->assertNotContainsTimeRanges([
            ['2020-03-12T23:20:00+02:00', '2020-03-12T23:30:00+02:00'],
        ], $choices);

        $this->assertContainsDays([
            "2020-03-12",
            "2020-03-13",
            "2020-03-14",
            "2020-03-15",
            "2020-03-17",
            "2020-03-18",
            "2020-03-19",
        ], $choices);

        Carbon::setTestNowAndTimezone(Carbon::parse('2020-03-12 23:30:00'));

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2020-03-13T13:20:00+02:00', '2020-03-13T13:30:00+02:00' ],
            [ '2020-03-13T13:30:00+02:00', '2020-03-13T13:40:00+02:00' ],
            [ '2020-03-13T15:30:00+02:00', '2020-03-13T15:40:00+02:00' ],
            [ '2020-03-13T15:50:00+02:00', '2020-03-13T16:00:00+02:00' ],

            [ '2020-03-13T20:20:00+02:00', '2020-03-13T20:30:00+02:00' ],
            [ '2020-03-13T20:30:00+02:00', '2020-03-13T20:40:00+02:00' ],
            [ '2020-03-13T23:00:00+02:00', '2020-03-13T23:10:00+02:00' ],
        ], $choices);

        $this->assertContainsDays([
            "2020-03-13",
            "2020-03-14",
            "2020-03-15",
            "2020-03-17",
            "2020-03-18",
            "2020-03-19",
        ], $choices);
    }

    public function testOnAnotherDay()
    {
        // 2019-08-04 is a Sunday
        Carbon::setTestNow(Carbon::parse('2019-08-04T12:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Tu-Sa 10:00-19:00"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsDays([
            '2019-08-06',
            '2019-08-07',
            '2019-08-08',
            '2019-08-09',
            '2019-08-10',
            // Sunday
            // Monday
        ], $choices);

        $this->assertContainsTimeRanges([
            [ '2019-08-06T10:00:00+02:00', '2019-08-06T10:10:00+02:00' ],
            [ '2019-08-06T18:50:00+02:00', '2019-08-06T19:00:00+02:00' ],
            [ '2019-08-07T10:00:00+02:00', '2019-08-07T10:10:00+02:00' ],
            [ '2019-08-07T10:30:00+02:00', '2019-08-07T10:40:00+02:00' ],
        ], $choices);
    }

    public function testWithUnconsecutiveDays()
    {
        Carbon::setTestNow(Carbon::parse('2019-08-04T12:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Tu 10:00-19:00", "Th-Sa 10:00-19:00"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertNotContainsDay('2019-08-07', $choices);

        $this->assertContainsTimeRanges([
            [ '2019-08-06T10:00:00+02:00', '2019-08-06T10:10:00+02:00' ],
            [ '2019-08-06T18:50:00+02:00', '2019-08-06T19:00:00+02:00' ],

            [ '2019-08-08T10:00:00+02:00', '2019-08-08T10:10:00+02:00' ],
            [ '2019-08-08T18:50:00+02:00', '2019-08-08T19:00:00+02:00' ],
        ], $choices);
    }

    public function testWithDelayedOrders()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:00+02:00'));

        $delay = (2 * 24 * 60); // should order two days in advance
        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal(), null, $delay);
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2017-10-06T17:30:00+02:00', '2017-10-06T17:40:00+02:00'],
            [ '2017-10-07T10:00:00+02:00', '2017-10-07T10:10:00+02:00' ],
            [ '2017-10-07T18:50:00+02:00', '2017-10-07T19:00:00+02:00' ],
        ], $choices);

        $this->assertNotContainsTimeRanges([
            ['2017-10-04T17:40:00+02:00', '2017-10-06T17:50:00+02:00']
        ], $choices);
    }

    public function testWithSecondRoundings()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:26+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal());
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2017-10-04T17:30:00+02:00', '2017-10-04T17:40:00+02:00' ],
            [ '2017-10-04T17:40:00+02:00', '2017-10-04T17:50:00+02:00' ],
            [ '2017-10-04T18:50:00+02:00', '2017-10-04T19:00:00+02:00' ],
        ], $choices);
    }

    public function testWithNoOpenings()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:26+02:00'));

        $choiceLoader = new AsapChoiceLoader([], $this->timeRegistry->reveal());
        $choiceList = $choiceLoader->loadChoiceList();

        $this->assertEquals([], $choiceList->getValues());
    }

    public function testWithClosingRules()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:26+02:00'));

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-10-04T18:29:26+02:00'));
        $closingRule->setEndDate(new \DateTime('2017-10-05T17:35:26+02:00'));

        $closingRules = new ArrayCollection();
        $closingRules->add($closingRule);

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal(), $closingRules);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();
        $values = $choiceList->getValues();

        $this->assertContainsTimeRanges([
            ['2017-10-04T17:40:00+02:00', '2017-10-04T17:50:00+02:00'],
            ['2017-10-04T17:50:00+02:00', '2017-10-04T18:00:00+02:00'],
            ['2017-10-04T18:10:00+02:00', '2017-10-04T18:20:00+02:00'],
            ['2017-10-05T17:40:00+02:00', '2017-10-05T17:50:00+02:00'],
            ['2017-10-05T17:50:00+02:00', '2017-10-05T18:00:00+02:00'],
            ['2017-10-05T18:10:00+02:00', '2017-10-05T18:20:00+02:00'],
            ['2017-10-05T18:30:00+02:00', '2017-10-05T18:40:00+02:00'],
            ['2017-10-05T18:40:00+02:00', '2017-10-05T18:50:00+02:00'],
        ], $choices);

        $this->assertNotContainsDates([
            '2017-10-04T18:30:00+02:00',
            '2017-10-05T18:05:00+02:00',
        ], $choices);
    }

    public function testWithClosingRulesOnSameDay()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T10:30:26+02:00'));

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-10-04T14:00:26+02:00'));
        $closingRule->setEndDate(new \DateTime('2017-10-04T16:00:26+02:00'));

        $closingRules = new ArrayCollection();
        $closingRules->add($closingRule);

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal(), $closingRules);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2017-10-04T16:00:00+02:00', '2017-10-04T16:10:00+02:00' ]
        ], $choices);

        $this->assertNotContainsTimeRanges([
            [ '2017-10-04T14:30:00+02:00', '2017-10-04T14:40:00+02:00' ]
        ], $choices);
    }

    public function testWithClosingRulesEmptyResult()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T16:30:26+02:00'));

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-10-04T14:00:26+02:00'));
        $closingRule->setEndDate(new \DateTime('2017-10-20T11:00:26+02:00'));

        $closingRules = new ArrayCollection();
        $closingRules->add($closingRule);

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal(), $closingRules);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertEmpty($choices);

        $this->assertNotContainsDates([
            '2017-10-04T14:30:00+02:00',
        ], $choices);
    }

    public function testUntilLastMinuteOfDay()
    {
        Carbon::setTestNow(Carbon::parse('2020-09-07T17:00:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Su 18:00-23:59"], $this->timeRegistry->reveal());
        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2020-09-07T18:00:00+02:00', '2020-09-07T18:10:00+02:00'],
            [ '2020-09-08T23:40:00+02:00', '2020-09-08T23:50:00+02:00' ],
            [ '2020-09-08T23:50:00+02:00', '2020-09-09T00:00:00+02:00' ],
        ], $choices);
    }

    public function testMultipleSlotsOnSameDayNotOpenNow()
    {
        Carbon::setTestNow(Carbon::parse('2020-09-07T19:00:00+02:00'));

        $choiceLoader = new AsapChoiceLoader([
            "We-Fr 12:00-14:00",
            "We-Sa 19:30-22:30"
        ], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-09-09T12:00:00+02:00', '2020-09-09T12:10:00+02:00'],
            ['2020-09-09T12:10:00+02:00', '2020-09-09T12:20:00+02:00'],
            ['2020-09-09T12:20:00+02:00', '2020-09-09T12:30:00+02:00'],
            ['2020-09-09T12:30:00+02:00', '2020-09-09T12:40:00+02:00'],
            ['2020-09-09T12:40:00+02:00', '2020-09-09T12:50:00+02:00'],
            ['2020-09-09T13:00:00+02:00', '2020-09-09T13:10:00+02:00'],
            ['2020-09-09T13:10:00+02:00', '2020-09-09T13:20:00+02:00'],
            ['2020-09-09T13:50:00+02:00', '2020-09-09T14:00:00+02:00'],

            ['2020-09-09T19:30:00+02:00', '2020-09-09T19:40:00+02:00'],
            ['2020-09-09T22:20:00+02:00', '2020-09-09T22:30:00+02:00'],

            ['2020-09-10T12:00:00+02:00', '2020-09-10T12:10:00+02:00'],
            ['2020-09-10T13:50:00+02:00', '2020-09-10T14:00:00+02:00'],

            ['2020-09-10T19:30:00+02:00', '2020-09-10T19:40:00+02:00'],
            ['2020-09-10T19:40:00+02:00', '2020-09-10T19:50:00+02:00'],
            ['2020-09-10T22:20:00+02:00', '2020-09-10T22:30:00+02:00'],
        ], $choices);
    }

    public function testOpensOnThursday()
    {
        Carbon::setTestNow(Carbon::parse('2020-12-23T09:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Th-Fr 09:30-14:30","Th-Fr 20:30-23:00","Sa-Su 09:30-14:45","Sa-Su 19:15-01:15"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2020-12-24T09:30:00+02:00', '2020-12-24T09:40:00+02:00' ]
        ], $choices);

        $this->assertNotContainsDay('2020-12-23', $choices);

        $this->assertNotContainsTimeRanges([
            [ '2020-12-23T10:00:00+02:00', '2020-12-23T10:10:00+02:00' ]
        ], $choices);
    }

    public function testIssue1007()
    {
        Carbon::setTestNow(Carbon::parse('2019-12-20T21:50:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Th-Fr 09:30-14:30","Th-Fr 20:30-23:00","Sa-Su 09:30-14:45","Sa-Su 19:15-01:15"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2019-12-20T22:00:00+02:00', '2019-12-20T22:10:00+02:00'],

        ], $choices);

        $this->assertNotContainsTimeRanges([
            [ '2019-12-20T23:50:00+02:00', '2019-12-21T00:00:00+02:00'],
            [ '2019-12-21T00:00:00+02:00', '2019-12-21T00:10:00+02:00' ]
        ], $choices);
    }

    public function testPreorderingDisabled()
    {
        Carbon::setTestNow(Carbon::parse('2020-12-23T09:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["We-Fr 11:30-14:30"], $this->timeRegistry->reveal(), null, 0, 10, false);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-12-23T11:30:00+02:00', '2020-12-23T11:40:00+02:00'],
            ['2020-12-23T14:00:00+02:00', '2020-12-23T14:10:00+02:00'],
            ['2020-12-23T14:10:00+02:00', '2020-12-23T14:20:00+02:00'],
            ['2020-12-23T14:20:00+02:00', '2020-12-23T14:30:00+02:00']
        ], $choices);

        $this->assertNotContainsDay('2020-12-24', $choices);
        $this->assertNotContainsDay('2020-12-25', $choices);
    }

    public function testRangeDuration()
    {
        Carbon::setTestNow(Carbon::parse('2020-12-23T09:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo 11:00-13:00"], $this->timeRegistry->reveal(), null, 0, 10);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-12-28T11:00:00+02:00', '2020-12-28T11:10:00+02:00'],
            ['2020-12-28T11:10:00+02:00', '2020-12-28T11:20:00+02:00'],
            ['2020-12-28T12:50:00+02:00', '2020-12-28T13:00:00+02:00'],
        ], $choices);

        $choiceLoader = new AsapChoiceLoader(["Mo 11:00-13:00"], $this->timeRegistry->reveal(), null, 0, 30);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-12-28T11:00:00+02:00', '2020-12-28T11:30:00+02:00'],
            ['2020-12-28T11:30:00+02:00', '2020-12-28T12:00:00+02:00'],
            ['2020-12-28T12:30:00+02:00', '2020-12-28T13:00:00+02:00'],
        ], $choices);

        $choiceLoader = new AsapChoiceLoader(["Mo 11:00-14:00"], $this->timeRegistry->reveal(), null, 0, 60);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-12-28T11:00:00+02:00', '2020-12-28T12:00:00+02:00'],
            ['2020-12-28T12:00:00+02:00', '2020-12-28T13:00:00+02:00'],
            ['2020-12-28T13:00:00+02:00', '2020-12-28T14:00:00+02:00'],
        ], $choices);
    }

    public function test30MinutesRange()
    {
        Carbon::setTestNow(Carbon::parse('2020-12-23T09:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo 11:15-13:30"], $this->timeRegistry->reveal(), null, 0, 30);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2020-12-28T11:30:00+02:00', '2020-12-28T12:00:00+02:00'],
            ['2020-12-28T12:00:00+02:00', '2020-12-28T12:30:00+02:00'],
            ['2020-12-28T12:30:00+02:00', '2020-12-28T13:00:00+02:00'],
            ['2020-12-28T13:00:00+02:00', '2020-12-28T13:30:00+02:00'],
        ], $choices);
    }

    public function testEscaleDuJapon()
    {
        // It's a Wednesday
        Carbon::setTestNowAndTimezone(Carbon::parse('2021-01-27 17:08:00'));

        $delay = 90; // 1h30
        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 11:30-14:00","Mo-Sa 18:00-20:30"], $this->timeRegistry->reveal(), null, $delay, 10);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $firstChoice = $choices[0];

        $range = $firstChoice->toTsRange();

        $this->assertEquals(new \DateTime('2021-01-27 18:40:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-27 18:50:00'), $range->getUpper());
    }

    public function test30MinutesRangeWithPriorNotice()
    {
        // It's a Thursday
        Carbon::setTestNowAndTimezone(Carbon::parse('2021-01-28 11:55:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Fr 11:30-14:30"], $this->timeRegistry->reveal(), null, 120, 30);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $firstChoice = $choices[0];
        $range = $firstChoice->toTsRange();

        $this->assertEquals(new \DateTime('2021-01-28 14:00:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-28 14:30:00'), $range->getUpper());

        Carbon::setTestNowAndTimezone(Carbon::parse('2021-01-28 12:05:00'));

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $firstChoice = $choices[0];
        $range = $firstChoice->toTsRange();

        $this->assertEquals(new \DateTime('2021-01-29 11:30:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-29 12:00:00'), $range->getUpper());
    }

    public function testPreorderingDisabledWith60MinutesDuration()
    {
        Carbon::setTestNow(Carbon::parse('2021-03-17 12:05:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 09:00-21:00"], $this->timeRegistry->reveal(), null, 0, 60, false);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $firstChoice = $choices[0];
        $range = $firstChoice->toTsRange();

        $this->assertEquals(new \DateTime('2021-03-17 13:00:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-03-17 14:00:00'), $range->getUpper());

        Carbon::setTestNow(Carbon::parse('2021-03-17 20:30:00'));

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(0, $choices);
    }

    public function testIssue2632()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T12:10:26+02:00'));

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-10-04T00:00:00+02:00'));
        $closingRule->setEndDate(new \DateTime('2017-10-04T12:40:00+02:00'));

        $closingRules = new ArrayCollection();
        $closingRules->add($closingRule);

        $choiceLoader = new AsapChoiceLoader([
            "Mo-Sa 11:30-14:00",
            "Mo-Sa 18:30-21:00"
        ], $this->timeRegistry->reveal(), $closingRules);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            ['2017-10-04T12:40:00+02:00', '2017-10-04T12:50:00+02:00'],
            ['2017-10-04T12:50:00+02:00', '2017-10-04T13:00:00+02:00'],
            ['2017-10-04T13:50:00+02:00', '2017-10-04T14:00:00+02:00'],
            ['2017-10-04T18:30:00+02:00', '2017-10-04T18:40:00+02:00'],
        ], $choices);

        $this->assertNotContainsTimeRanges([
            ['2017-10-04T12:00:00+02:00', '2017-10-04T12:10:00+02:00'],
            ['2017-10-04T12:10:00+02:00', '2017-10-04T12:20:00+02:00'],
            ['2017-10-04T12:20:00+02:00', '2017-10-04T12:30:00+02:00'],
            ['2017-10-04T12:30:00+02:00', '2017-10-04T12:40:00+02:00'],
            ['2017-10-04T14:00:00+02:00', '2017-10-04T14:10:00+02:00'],
            ['2017-10-04T17:50:00+02:00', '2017-10-04T18:00:00+02:00'],
        ], $choices);
    }

    public function testSameDayWithPreparationAndShippingOffset()
    {
        $this->timeRegistry->getAveragePreparationTime()->willReturn(15);
        $this->timeRegistry->getAverageShippingTime()->willReturn(15);

        // 2017-10-04 is a Wednesday
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:30:00+02:00'));

        $choiceLoader = new AsapChoiceLoader(["Mo-Sa 10:00-19:00"], $this->timeRegistry->reveal());

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertContainsTimeRanges([
            [ '2017-10-04T17:30:00+02:00', '2017-10-04T17:40:00+02:00' ],
            [ '2017-10-04T18:00:00+02:00', '2017-10-04T18:10:00+02:00' ],
            [ '2017-10-04T18:40:00+02:00', '2017-10-04T18:50:00+02:00' ],
            [ '2017-10-04T18:50:00+02:00', '2017-10-04T19:00:00+02:00' ],
            [ '2017-10-04T19:00:00+02:00', '2017-10-04T19:10:00+02:00' ],
            [ '2017-10-04T19:10:00+02:00', '2017-10-04T19:20:00+02:00' ],
            [ '2017-10-04T19:20:00+02:00', '2017-10-04T19:30:00+02:00' ],
            [ '2017-10-05T10:00:00+02:00', '2017-10-05T10:10:00+02:00' ],
        ], $choices);

        $this->assertContainsDays([
            "2017-10-04",
            "2017-10-05",
            "2017-10-06",
            "2017-10-07",
            // Sunday
            "2017-10-09",
            "2017-10-10",
            "2017-10-11",
        ], $choices);
    }
}
