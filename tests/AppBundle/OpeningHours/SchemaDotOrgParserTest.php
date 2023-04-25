<?php

namespace Tests\AppBundle\OpeningHours;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\SchemaDotOrgParser;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Spatie\OpeningHours\OpeningHours;

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

    public function testParseExceptions3()
    {
        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2020-12-31 15:00:00'));
        $closingRule->setEndDate(new \DateTime('2021-01-01 22:30:00'));

        $closingRules->add($closingRule);

        $exceptions = SchemaDotOrgParser::parseExceptions(
          $closingRules,
          SchemaDotOrgParser::parseCollection(['Mo-Sa 11:30-13:30', 'Mo-Sa 19:00-21:30'])
        );

        $this->assertArrayHasKey('2020-12-31', $exceptions);
        $this->assertEquals(['11:30-13:30'], $exceptions['2020-12-31']);

        $this->assertArrayHasKey('2021-01-01', $exceptions);
        $this->assertEmpty($exceptions['2021-01-01']);
    }

    public function testOverlappingRanges()
    {
        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-09-02 12:30:00'));
        $closingRule->setEndDate(new \DateTime('2017-09-04 11:00:00'));

        $closingRules->add($closingRule);

        $rules = [
            ["Th-Sa 10:00-15:30","Th-Sa 18:30-21:30","We 09:45-14:30","Su 12:30-15:30","Th-Fr 10:00-18:30"],
            ["We 09:45-14:30","Th-Sa 10:00-18:30"],
            ["Mo-Sa 12:00-15:45","Tu-We,Sa 20:15-22:45","Th-Fr 12:00-18:30"],
            ["Tu-We,Sa 20:15-22:45","Th-Fr 12:00-18:30"],
            ["Tu-Th 12:45-15:30","Tu-Sa 19:15-21:30","Fr-Su 12:45-16:00","Th-Fr 12:45-18:30"],
            ["Tu-Th 12:45-15:30","Tu-Sa 19:15-21:30","Fr-Su 12:45-16:00"],
            ["Mo-Su 10:30-16:00","Th-Fr 10:30-18:30"],
            ["Mo-Su 10:30-16:00"],
            ["Mo-We,Sa 07:30-16:00","Tu-We,Sa 19:00-21:00","Su 12:00-15:30","Th-Fr 09:00-18:00"],
        ];

        foreach ($rules as $value) {
            $oh = SpatieOpeningHoursRegistry::get($value, $closingRules);
            $this->assertInstanceOf(OpeningHours::class, $oh);
        }
    }

    public function testIssue2632()
    {
        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime("2021-07-05T12:40:00+02:00"));
        $closingRule->setEndDate(new \DateTime("2021-07-14T12:40:00+02:00"));

        $closingRules->add($closingRule);

        $exceptions = SchemaDotOrgParser::parseExceptions(
            $closingRules,
            SchemaDotOrgParser::parseCollection([
                "Mo-Sa 11:30-14:00",
                "Mo-Sa 18:30-21:00"
            ])
        );

        $this->assertArrayHasKey('2021-07-05', $exceptions);
        $this->assertEquals([
            '11:30-12:40'
        ], $exceptions['2021-07-05']);

        $this->assertArrayHasKey('2021-07-14', $exceptions);
        $this->assertEquals([
            '12:40-14:00',
            '18:30-21:00'
        ], $exceptions['2021-07-14']);
    }

    public function testRayon9Issue()
    {
        $closingRules = new ArrayCollection();

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime("2022-06-23T00:00:00+02:00"));
        $closingRule->setEndDate(new \DateTime("2022-06-25T12:00:00+02:00"));

        $closingRules->add($closingRule);

        $exceptions = SchemaDotOrgParser::parseExceptions(
            $closingRules,
            SchemaDotOrgParser::parseCollection([
                "Sa 09:00-12:00",
            ])
        );

        $this->assertEmpty($exceptions['2022-06-25']);
    }
}
