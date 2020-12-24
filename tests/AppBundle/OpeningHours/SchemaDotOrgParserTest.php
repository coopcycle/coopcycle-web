<?php

namespace Tests\AppBundle\OpeningHours;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\SchemaDotOrgParser;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class SchemaDotOrgParserTest extends TestCase
{
  public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testParseCollection()
    {
        $data = SchemaDotOrgParser::parseCollection(["Mo-Sa 10:00-19:00"]);

        $this->assertEquals([
          'monday'    => ['10:00-19:00'],
          'tuesday'   => ['10:00-19:00'],
          'wednesday' => ['10:00-19:00'],
          'thursday'  => ['10:00-19:00'],
          'friday'    => ['10:00-19:00'],
          'saturday'  => ['10:00-19:00'],
          'sunday'    => [],
        ], $data);

        $data = SchemaDotOrgParser::parseCollection(["Mo-Fr 10:00-13:00", "Mo-Fr 15:00-19:00", "Sa-Su 19:00-23:00"]);

        $this->assertEquals([
          'monday'    => ['10:00-13:00', '15:00-19:00'],
          'tuesday'   => ['10:00-13:00', '15:00-19:00'],
          'wednesday' => ['10:00-13:00', '15:00-19:00'],
          'thursday'  => ['10:00-13:00', '15:00-19:00'],
          'friday'    => ['10:00-13:00', '15:00-19:00'],
          'saturday'  => ['19:00-23:00'],
          'sunday'    => ['19:00-23:00'],
        ], $data);

        $data = SchemaDotOrgParser::parseCollection(["Mo 10:00-13:00"]);

        $this->assertEquals([
          'monday'    => ['10:00-13:00'],
          'tuesday'   => [],
          'wednesday' => [],
          'thursday'  => [],
          'friday'    => [],
          'saturday'  => [],
          'sunday'    => [],
        ], $data);
    }

    public function testParseCollectionWithWeekend()
    {
        $data = SchemaDotOrgParser::parseCollection(["Th-Fr 09:30-14:30","Th-Fr 20:30-23:00","Sa-Su 09:30-14:45","Sa-Su 19:15-01:15"]);

        $this->assertEquals([
          'monday'    => [],
          'tuesday'   => [],
          'wednesday' => [],
          'thursday'  => ['09:30-14:30', '20:30-23:00'],
          'friday'    => ['09:30-14:30', '20:30-23:00'],
          'saturday'  => ['09:30-14:45', '19:15-01:15'],
          'sunday'    => ['09:30-14:45', '19:15-01:15'],
        ], $data);
    }

    public function testParseExceptions()
    {
        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-09-02 09:00:00'));
        $closingRule->setEndDate(new \DateTime('2017-09-04 11:00:00'));

        $closingRules->add($closingRule);

        $exceptions = SchemaDotOrgParser::parseExceptions(
          $closingRules,
          SchemaDotOrgParser::parseCollection(['Mo-Sa 11:30-14:30'])
        );

        $this->assertArrayHasKey('2017-09-02', $exceptions);
        $this->assertArrayHasKey('2017-09-03', $exceptions);

        $this->assertArrayNotHasKey('2017-09-04', $exceptions);
    }

    public function testParseExceptions2()
    {
        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-09-02 12:30:00'));
        $closingRule->setEndDate(new \DateTime('2017-09-04 11:00:00'));

        $closingRules->add($closingRule);

        $exceptions = SchemaDotOrgParser::parseExceptions(
          $closingRules,
          SchemaDotOrgParser::parseCollection(['Mo-Sa 11:30-14:30'])
        );

        $this->assertArrayHasKey('2017-09-02', $exceptions);
        $this->assertEquals(['11:30-12:30'], $exceptions['2017-09-02']);

        $this->assertArrayHasKey('2017-09-03', $exceptions);
        $this->assertEmpty($exceptions['2017-09-03']);

        $this->assertArrayNotHasKey('2017-09-04', $exceptions);
    }
}
