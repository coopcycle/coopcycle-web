<?php

namespace AppBundle\Entity\TimeSlot;

use AppBundle\Entity\TimeSlot;

interface TimeSlotAwareInterface
{
    public function getTimeSlot(): ?TimeSlot;
}
