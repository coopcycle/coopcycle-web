<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Task;
use AppBundle\DataType\TsRange;
use Carbon\Carbon;

class TimeSlotChoice
{
    private $date;
    private $timeRange = ['00:00', '23:59'];

    public function __construct(\DateTimeInterface $date, string $timeRange)
    {
        $this->date = $date;
        $this->timeRange = array_map(function (string $time) {
            return substr($time, 0, 5);
        }, explode('-', $timeRange));
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @return array
     */
    public function getTimeRange()
    {
        return $this->timeRange;
    }

    public function hasBegun(\DateTimeInterface $now = null, string $priorNotice = null)
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        [ $start, $end ] = $this->timeRange;
        [ $hour, $minute ] = explode(':', $start);

        $after = clone $this->date;

        $after->setTime($hour, $minute);

        if ($priorNotice) {
            $after->modify(sprintf('-%s', $priorNotice));
        }

        return $now >= $after;
    }

    public function hasFinished(\DateTimeInterface $now = null, string $priorNotice = null)
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        [ $start, $end ] = $this->timeRange;
        [ $hour, $minute ] = explode(':', $end);

        $before = clone $this->date;

        $before->setTime($hour, $minute);

        if ($priorNotice) {
            $before->modify(sprintf('-%s', $priorNotice));
        }

        return $now >= $before;
    }

    public function applyToTask(Task $task)
    {
        $datePeriod = $this->toDatePeriod();

        $task->setDoneAfter($datePeriod->start);
        $task->setDoneBefore($datePeriod->end);
    }

    public function toDatePeriod(): \DatePeriod
    {
        [ $startHour, $startMinute ] = explode(':', $this->timeRange[0]);
        [ $endHour, $endMinute ] = explode(':', $this->timeRange[1]);

        $start = clone $this->date;
        $end = clone $this->date;

        $start->setTime($startHour, $startMinute);
        $end->setTime($endHour, $endMinute);

        return new \DatePeriod($start, $end->diff($start), $end);
    }

    public static function fromTask(Task $task): self
    {
        $after = Carbon::instance($task->getDoneAfter());
        $before = Carbon::instance($task->getDoneBefore());

        if (!$after->isSameDay($before)) {
            // TODO Throw Exception

        }

        return new self($before, sprintf('%s-%s', $after->format('H:i'), $before->format('H:i')));
    }

    public function toTsRange(): TsRange
    {
        [ $startHour, $startMinute ] = explode(':', $this->timeRange[0]);
        [ $endHour, $endMinute ] = explode(':', $this->timeRange[1]);

        $lower = clone $this->date;
        $upper = clone $this->date;

        $lower->setTime($startHour, $startMinute);
        $upper->setTime($endHour, $endMinute);

        $range = new TsRange();
        $range->lower = $lower;
        $range->upper = $upper;

        return $range;
    }

    public function __toString()
    {
        return sprintf('%s %s', $this->date->format('Y-m-d'), implode('-', $this->timeRange));
    }
}
