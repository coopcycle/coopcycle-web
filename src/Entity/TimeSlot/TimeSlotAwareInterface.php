<?php

namespace AppBundle\Entity\TimeSlot;

use AppBundle\Entity\TimeSlot;

interface TimeSlotAwareInterface
{
    //FIXME: string is returned only for the sake of the example
//    public function getTimeSlot(): TimeSlot;
    public function getTimeSlot(): string;
}
