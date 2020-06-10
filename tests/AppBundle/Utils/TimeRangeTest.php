<?php

namespace AppBundle\Utils;

use PHPUnit\Framework\TestCase;
use AppBundle\Exception\TimeRange\EmptyRangeException;
use AppBundle\Exception\TimeRange\NoWeekdayException;
use AppBundle\Utils\TimeRange;

class TimeRangeTest extends TestCase
{
    public function testEmptyTimeRangeThrowsException()
    {
        $this->expectException(EmptyRangeException::class);

        $timeRange = TimeRange::create('');
    }

    public function invalidRangeProvider()
    {
        return [
            ['Fo-Ba', \RuntimeException::class, 'Unexpected day Fo'],
            ['Mo-Ba', \RuntimeException::class, 'Unexpected day Ba'],
            ['Ba 10:00-11:00', \RuntimeException::class, 'Unexpected day Ba'],
            ['11:45-14:45', NoWeekdayException::class, '']
        ];
    }

    /**
     * @dataProvider invalidRangeProvider
     */
    public function testInvalidTimeRangeThrowsException($range, $exception, $message)
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $timeRange = TimeRange::create($range);
    }

    public function test247()
    {
        $timeRange = TimeRange::create('Mo-Su 00:00-23:59');

        $this->assertEquals(new \DateTime('2017-05-16 06:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 06:30')));
        $this->assertNull($timeRange->getNextClosingDate(new \DateTime('2017-05-16 06:30')));
    }

    public function testIsOpen()
    {
        $timeRange = TimeRange::create('Mo-Sa 11:45-14:45');

        // Monday
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-15 11:44')));
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-15 11:45')));
        $this->assertTrue($timeRange->isOpen(new \DateTime('2017-05-15 12:30')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-15 11:25')));
        $this->assertFalse($timeRange->isOpen(new \DateTime('2017-05-15 14:45')));

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
        $timeRange = TimeRange::create('Mo-Sa 20:45-01:00');

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
        $timeRange = TimeRange::create('Mo-Sa 11:45-14:45');

        $this->assertEquals(new \DateTime('2017-05-16 11:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 06:30')));
        $this->assertEquals(new \DateTime('2017-05-17 11:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 14:45')));
        $this->assertEquals(new \DateTime('2017-05-17 11:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 15:30')));
        $this->assertEquals(new \DateTime('2017-05-16 12:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 12:30')));

        $timeRange = TimeRange::create('Mo-Sa 20:45-01:00');

        $this->assertEquals(new \DateTime('2017-05-16 20:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 13:30')));
        $this->assertEquals(new \DateTime('2017-05-16 20:45'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 01:30')));
        $this->assertEquals(new \DateTime('2017-05-16 21:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 21:30')));
        $this->assertEquals(new \DateTime('2017-05-16 21:15'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 21:13')));
        $this->assertEquals(new \DateTime('2017-05-16 21:30'), $timeRange->getNextOpeningDate(new \DateTime('2017-05-16 21:19')));
    }

    public function testGetNextClosingDate()
    {
        $timeRange = TimeRange::create('Mo-Sa 11:45-14:45');
        $this->assertEquals(new \DateTime('2017-05-16 14:45'), $timeRange->getNextClosingDate(new \DateTime('2017-05-16 11:45:00')));

        $timeRange = TimeRange::create('Tu 10:00-19:00');
        $this->assertEquals(new \DateTime('2019-08-06 19:00'), $timeRange->getNextClosingDate(new \DateTime('2019-08-04 12:30')));
    }
}
