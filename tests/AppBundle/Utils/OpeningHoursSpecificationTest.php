<?php

namespace AppBundle\Utils;

use AppBundle\Entity\ClosingRule;
use PHPUnit\Framework\TestCase;
use AppBundle\Utils\OpeningHoursSpecification;

class OpeningHoursSpecificationTest extends TestCase
{
    public function testFromOpeningHours()
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
}
