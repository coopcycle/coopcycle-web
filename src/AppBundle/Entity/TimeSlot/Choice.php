<?php

namespace AppBundle\Entity\TimeSlot;

use AppBundle\Entity\Task;
use Carbon\Carbon;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

class Choice
{
    use Timestampable;

    private $id;
    private $timeSlot;

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $startTime;

    /**
     * @var string
     * @Groups({"time_slot"})
     */
    private $endTime;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getTimeSlot()
    {
        return $this->timeSlot;
    }

    /**
     * @param mixed $timeSlot
     *
     * @return self
     */
    public function setTimeSlot($timeSlot)
    {
        $this->timeSlot = $timeSlot;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param mixed $startTime
     *
     * @return self
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @param mixed $endTime
     *
     * @return self
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function apply(Task $task, \DateTime $date = null)
    {
        [ $start, $end ] = $this->toDateTime($date);

        $task->setDoneAfter($start);
        $task->setDoneBefore($end);
    }

    public function toDateTime(\DateTime $date = null)
    {
        if (null === $date) {
            $date = Carbon::now();
        }

        [ $startHour, $startMinute ] = explode(':', $this->getStartTime());
        [ $endHour, $endMinute ] = explode(':', $this->getEndTime());

        $after = clone $date;
        $before = clone $date;

        $after->setTime($startHour, $startMinute);
        $before->setTime($endHour, $endMinute);

        return [ $after, $before ];
    }
}
