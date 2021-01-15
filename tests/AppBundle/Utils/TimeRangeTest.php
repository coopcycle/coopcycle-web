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
}
