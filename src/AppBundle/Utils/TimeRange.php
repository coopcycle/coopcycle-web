<?php

namespace AppBundle\Utils;

use AppBundle\Exception\TimeRange\EmptyRangeException;
use AppBundle\Exception\TimeRange\NoWeekdayException;
use Carbon\Carbon;

class TimeRange
{
    private $weekdays = [];
    private $timeRanges = [];
    private $is247 = false;

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

    public function __construct(string $range = null)
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

    private function checkWeekday(\DateTime $date)
    {
        foreach ($this->weekdays as $weekday) {
            if (array_search($weekday, self::DAYS) === (int) $date->format('N')) {
                return true;
            }
        }

        return false;
    }

    private function checkTime(\DateTime $date)
    {
        foreach ($this->timeRanges as $timeRange) {
            list($open, $close) = explode('-', $timeRange);

            list($startHour, $startMinute) = explode(':', $open);
            list($endHour, $endMinute) = explode(':', $close);

            $openDate = clone $date;
            $openDate->setTime($startHour, $startMinute);

            $closeDate = clone $date;
            $closeDate->setTime($endHour, $endMinute);

            if ($closeDate <= $openDate && $date >= $openDate) {
                $closeDate->modify('+1 day');
            }

            if ($closeDate <= $openDate && $date <= $closeDate) {
                $openDate->modify('-1 day');
            }

            if ($date >= $openDate && $date < $closeDate) {
                return true;
            }
        }

        return false;
    }

    public function isOpen(\DateTime $date = null)
    {
        if (!$date) {
            $date = Carbon::now();
        }

        if ($this->is247) {
            return true;
        }

        if (!$this->checkWeekday($date)) {
            return false;
        }

        if (!$this->checkTime($date)) {
            return false;
        }

        return true;
    }

    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $date = clone $now;

        // round to next minute
        $s = 60;
        $date->setTimestamp($s * ceil($date->getTimestamp() / $s));

        while (($date->format('i') % 15) !== 0) {
            $date->modify('+1 minute');
        }

        while (!$this->isOpen($date)) {
            $date->modify('+15 minutes');
        }

        return $date;
    }

    public function getNextClosingDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if ($this->is247) {
            return null;
        }

        $date = clone $now;

        // round to next minute
        $s = 60;
        $date->setTimestamp($s * ceil($date->getTimestamp() / $s));

        while (($date->format('i') % 15) !== 0) {
            $date->modify('+1 minute');
        }

        if (!$this->isOpen($date)) {
            $date = $this->getNextOpeningDate($date);
        }

        while ($this->isOpen($date)) {
            $date->modify('+15 minutes');
        }

        return $date;
    }
}
