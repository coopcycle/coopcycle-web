<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\TimeSlot;
use Symfony\Component\Serializer\Annotation\Groups;

trait TimeSlotAwareTrait
{
    /**
     * @Groups({"store"})
     */
    protected $timeSlot;

    public function setTimeSlot(?TimeSlot $timeSlot)
    {
        $this->timeSlot = $timeSlot;

        return $this;
    }

    public function getTimeSlot(): ?TimeSlot
    {
        return $this->timeSlot;
    }
}
