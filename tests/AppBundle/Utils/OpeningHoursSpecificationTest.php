<?php

namespace AppBundle\Utils;

use AppBundle\Entity\ClosingRule;
use PHPUnit\Framework\TestCase;
use AppBundle\Utils\OpeningHoursSpecification;

class OpeningHoursSpecificationTest extends TestCase
{
    public function testFromOpeningHoursWithRangeOfDays()
    {
        $openingHours = ['Mo-Fr 10:00-19:00', 'Sa 10:00-22:00', 'Su 10:00-21:00'];

        $openingHoursSpecification = OpeningHoursSpecification::fromOpeningHours($openingHours);

        $this->assertCount(3, $openingHoursSpecification);

        $this->assertEquals(['Monday','Tuesday','Wednesday','Thursday','Friday'], $openingHoursSpecification[0]->dayOfWeek);
        $this->assertEquals('10:00', $openingHoursSpecification[0]->opens);
        $this->assertEquals('19:00', $openingHoursSpecification[0]->closes);

        $this->assertEquals(['Saturday'], $openingHoursSpecification[1]->dayOfWeek);
        $this->assertEquals('10:00', $openingHoursSpecification[1]->opens);
        $this->assertEquals('22:00', $openingHoursSpecification[1]->closes);

        $this->assertEquals(['Sunday'], $openingHoursSpecification[2]->dayOfWeek);
        $this->assertEquals('10:00', $openingHoursSpecification[2]->opens);
        $this->assertEquals('21:00', $openingHoursSpecification[2]->closes);
    }

    public function testFromOpeningHoursWithListOfDays()
    {
        $openingHours = ["Tu,Th 18:00-20:00", "Tu,Th 20:00-20:30"];

        $openingHoursSpecification = OpeningHoursSpecification::fromOpeningHours($openingHours);

        $this->assertCount(2, $openingHoursSpecification);

        $this->assertEquals(['Tuesday','Thursday'], $openingHoursSpecification[0]->dayOfWeek);
        $this->assertEquals('18:00', $openingHoursSpecification[0]->opens);
        $this->assertEquals('20:00', $openingHoursSpecification[0]->closes);

        $this->assertEquals(['Tuesday','Thursday'], $openingHoursSpecification[1]->dayOfWeek);
        $this->assertEquals('20:00', $openingHoursSpecification[1]->opens);
        $this->assertEquals('20:30', $openingHoursSpecification[1]->closes);
    }

    public function testFromOpeningHoursWithDiscontinuedRange()
    {
        $openingHours = ['Tu-Th,Sa 16:00-18:00'];

        $openingHoursSpecification = OpeningHoursSpecification::fromOpeningHours($openingHours);

        $this->assertCount(1, $openingHoursSpecification);

        $this->assertEquals(['Tuesday', 'Wednesday', 'Thursday', 'Saturday'], $openingHoursSpecification[0]->dayOfWeek);
        $this->assertEquals('16:00', $openingHoursSpecification[0]->opens);
        $this->assertEquals('18:00', $openingHoursSpecification[0]->closes);
    }

    public function testFromOpeningHoursWithSingleDay()
    {
        $openingHours = ['Tu 16:00-18:00'];

        $openingHoursSpecification = OpeningHoursSpecification::fromOpeningHours($openingHours);

        $this->assertCount(1, $openingHoursSpecification);

        $this->assertEquals(['Tuesday'], $openingHoursSpecification[0]->dayOfWeek);
        $this->assertEquals('16:00', $openingHoursSpecification[0]->opens);
        $this->assertEquals('18:00', $openingHoursSpecification[0]->closes);
    }

    public function testFromClosingRule()
    {
        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2018-09-21 19:00:00'));
        $closingRule->setEndDate(new \DateTime('2018-09-22 12:30:00'));

        $openingHoursSpecification = OpeningHoursSpecification::fromClosingRule($closingRule);

        $this->assertEquals('2018-09-21', $openingHoursSpecification->validFrom);
        $this->assertEquals('2018-09-22', $openingHoursSpecification->validThrough);
        $this->assertEquals('12:30', $openingHoursSpecification->opens);
        $this->assertEquals('19:00', $openingHoursSpecification->closes);
    }

    public function testFromOpeningHoursWithEmptyDays()
    {
        $openingHours = ['16:00-18:00'];

        $openingHoursSpecification = OpeningHoursSpecification::fromOpeningHours($openingHours);

        $this->assertCount(1, $openingHoursSpecification);

        $this->assertEquals([], $openingHoursSpecification[0]->dayOfWeek);
        $this->assertEquals('16:00', $openingHoursSpecification[0]->opens);
        $this->assertEquals('18:00', $openingHoursSpecification[0]->closes);
    }
}
