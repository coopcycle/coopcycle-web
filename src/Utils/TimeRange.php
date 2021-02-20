<?php

namespace AppBundle\Utils;

use AppBundle\Exception\TimeRange\EmptyRangeException;
use AppBundle\Exception\TimeRange\NoWeekdayException;

class TimeRange
{
    private $weekdays = [];
    private $timeRanges = [];
    private $is247 = false;

    private static $objectCache = [];

    private $checkWeekdayCache = [];
    private $checkTimeCache = [];

    const TIME_RANGE_247 = 'Mo-Su 00:00-23:59';

    const DAYS = [
        1 => 'Mo',
        2 => 'Tu',
        3 => 'We',
        4 => 'Th',
        5 => 'Fr',
        6 => 'Sa',
        7 => 'Su'
    ];

    private function __construct(string $range = null)
    {
        $range = trim($range);

        if (empty($range)) {
            throw new EmptyRangeException('$range must be a non-empty string');
        }

        if (self::TIME_RANGE_247 === $range) {
            $this->is247 = true;
            return;
        }

        $parts = preg_split('/[\s,]+/', $range);

        $days = $hours = [];
        foreach ($parts as $part) {
            if (preg_match('/([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})/', $part)) {
                $hours[] = $part;
            } else {
                $days[] = $part;
            }
        }

        $weekdays = [];
        foreach ($days as $day) {
            if (false !== strpos($day, '-')) {
                list($start, $end) = explode('-', $day);
                $startIndex = array_search($start, self::DAYS);
                $endIndex = array_search($end, self::DAYS);
                if (false === $startIndex) {
                    throw new \RuntimeException(sprintf('Unexpected day %s', $start));
                }
                if (false === $endIndex) {
                    throw new \RuntimeException(sprintf('Unexpected day %s', $end));
                }
                for ($i = $startIndex; $i <= $endIndex; $i++) {
                    $weekdays[] = self::DAYS[$i];
                }
            } else {
                if (false === array_search($day, self::DAYS)) {
                    throw new \RuntimeException(sprintf('Unexpected day %s', $day));
                }
                $weekdays[] = $day;
            }
        }

        if (empty($weekdays)) {
            throw new NoWeekdayException();
        }

        $this->timeRanges = $hours;
        $this->weekdays = $weekdays;
    }

    public static function create(string $range)
    {
        if (!isset(self::$objectCache[$range])) {
            self::$objectCache[$range] = new TimeRange($range);
        }

        return self::$objectCache[$range];
    }
}
