<?php

namespace AppBundle\Utils;

use PHPUnit\Framework\TestCase;
use AppBundle\Utils\TimeRange;

class TimeRangeTest extends TestCase
{
    public function testEmptyTimeRangeThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        $timeRange = new TimeRange('');
    }


    public function invalidRangeProvider()
    {
        return [
            ['Fo-Ba', 'Unexpected day Fo'],
            ['Mo-Ba', 'Unexpected day Ba'],
            ['Ba 10:00-11:00', 'Unexpected day Ba'],
        ];
    }

    /**
     * @dataProvider invalidRangeProvider
     */
    public function testInvalidTimeRangeThrowsException($range, $message)
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $timeRange = new TimeRange($range);
    }

    public function testIsOpen()
    {
        $timeRange = new TimeRange('Mo-Sa 11:45-14:45');

        // Monday
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-15 12:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-15 11:25')));

        // Tuesday
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-16 13:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-16 15:30')));

        // Wednesday
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-17 13:45')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-17 19:30')));

        // Sunday
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-21 12:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-21 00:30')));
    }

    public function testIsOpenOverlap()
    {
        $timeRange = new TimeRange('Mo-Sa 20:45-01:00');

        // Monday
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-15 21:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-15 20:15')));

        // Tuesday
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-17 00:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-16 15:30')));

        // Wednesday
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-17 13:45')));

        // Sunday
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-21 12:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-21 00:30')));
    }

    public function testGetNextOpeningDate()
    {
        $timeRange = new TimeRange('Mo-Sa 11:45-14:45');

        $this->assertEquals(new \DateTime('2017-05-16 11:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 06:30')));
        $this->assertEquals(new \DateTime('2017-05-16 14:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 14:45')));
        $this->assertEquals(new \DateTime('2017-05-17 11:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 15:30')));
        $this->assertEquals(new \DateTime('2017-05-16 12:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 12:30')));

        $timeRange = new TimeRange('Mo-Sa 20:45-01:00');

        $this->assertEquals(new \DateTime('2017-05-16 20:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 13:30')));
        $this->assertEquals(new \DateTime('2017-05-16 20:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 01:30')));
        $this->assertEquals(new \DateTime('2017-05-16 21:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 21:30')));
        $this->assertEquals(new \DateTime('2017-05-16 21:15'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 21:13')));
        $this->assertEquals(new \DateTime('2017-05-16 21:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 21:19')));
    }
}
