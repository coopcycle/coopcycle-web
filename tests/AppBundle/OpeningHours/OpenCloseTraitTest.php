<?php

namespace Tests\AppBundle\OpeningHours;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class OpenCloseTraitTest extends TestCase implements OpenCloseInterface
{
    use OpenCloseTrait;

    private $openingHours = [];
    private $closingRules;

    public function setUp(): void
    {
        $this->closingRules = new ArrayCollection();
    }

    public function getOpeningHours($method = 'delivery')
    {
        return $this->openingHours;
    }

    public function getClosingRules()
    {
        return $this->closingRules;
    }

    public function setShippingOptionsDays(int $shippingOptionsDays)
    {
        return 2;
    }

    public function getOrderingDelayMinutes()
    {
        return 0;
    }

    public function testGetNextClosingDate()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $this->assertEquals(
            new \DateTime('2019-08-05T19:00:00+02:00'),
            $this->getNextClosingDate(new \DateTime('2019-08-05T12:00:00+02:00'))
        );
    }

    public function testGetNextClosingDateWithClosingRules()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2019-08-05T12:00:00+02:00'));
        $closingRule->setEndDate(new \DateTime('2019-08-06T12:00:00+02:00'));

        $this->closingRules->add($closingRule);

        $this->assertEquals(
            new \DateTime('2019-08-05T12:00:00+02:00'),
            $this->getNextClosingDate(new \DateTime('2019-08-05T11:00:00+02:00'))
        );
    }

    public function testGetNextOpeningDateWithHolidays()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2018-12-24T00:00:00+02:00'));
        $closingRule->setEndDate(new \DateTime('2019-01-01T10:00:00+02:00'));

        $this->closingRules->add($closingRule);

        $now = new \DateTime('2018-12-24T12:00:00+02:00');

        $nextOpeningDate = $this->getNextOpeningDate($now);

        $this->assertEquals(new \DateTime('2019-01-01T10:00:00+02:00'), $nextOpeningDate);
    }

    public function testIsOpen()
    {
        $this->openingHours = ['Mo-Sa 11:45-14:45'];

        // Monday
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-15 11:44')));
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-15 11:45')));
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-15 12:30')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-15 11:25')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-15 14:45')));

        // Tuesday
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-16 13:30')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-16 15:30')));

        // Wednesday
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-17 13:45')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-17 19:30')));

        // Sunday
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-21 12:30')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-21 00:30')));
    }

    public function testIsOpenOverlap()
    {
        $this->openingHours = ['Mo-Sa 20:45-01:00'];

        // Monday
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-15 21:30')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-15 20:15')));

        // Tuesday
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-17 00:30')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-16 15:30')));

        // Wednesday
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-17 13:45')));

        // Saturday
        $this->assertTrue($this->isOpen(new \DateTime('2017-05-21 00:30')));

        // Sunday
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-21 12:30')));
        $this->assertFalse($this->isOpen(new \DateTime('2017-05-22 00:30')));

    }

    public function testIsOpenWithClosingRules()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2019-08-05T12:00:00+02:00'));
        $closingRule->setEndDate(new \DateTime('2019-08-06T12:00:00+02:00'));

        $this->closingRules->add($closingRule);

        $this->assertFalse(
            $this->isOpen(new \DateTime('2019-08-05T12:00:00+02:00'))
        );
        $this->assertTrue(
            $this->isOpen(new \DateTime('2019-08-06T13:00:00+02:00'))
        );
    }

    public function testGetNextOpeningDate()
    {
        $this->openingHours = ['Mo-Sa 11:45-14:45'];

        $this->assertEquals(new \DateTime('2017-05-16 11:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 06:30')));
        $this->assertEquals(new \DateTime('2017-05-17 11:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 14:45')));
        $this->assertEquals(new \DateTime('2017-05-17 11:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 15:30')));
        $this->assertEquals(new \DateTime('2017-05-17 11:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 12:30')));
    }

    public function testGetNextOpeningDateOverlap()
    {
        $this->openingHours = ['Mo-Sa 20:45-01:00'];

        $this->assertEquals(new \DateTime('2017-05-16 20:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 13:30')));
        $this->assertEquals(new \DateTime('2017-05-16 20:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 01:30')));
        $this->assertEquals(new \DateTime('2017-05-17 20:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 21:30')));
        $this->assertEquals(new \DateTime('2017-05-17 20:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 21:13')));
        $this->assertEquals(new \DateTime('2017-05-17 20:45'), $this->getNextOpeningDate(new \DateTime('2017-05-16 21:19')));
    }

    public function testGetNextOpeningDateWithEmptyOpeningHours()
    {
        $this->openingHours = [];

        $now = new \DateTime('2021-01-05T12:00:00+02:00');

        $this->assertNull($this->getNextOpeningDate($now));
    }
}
