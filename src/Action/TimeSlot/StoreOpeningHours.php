<?php

namespace AppBundle\Action\TimeSlot;

class StoreOpeningHours extends AbstractTimeSlotChoices
{
    public function __invoke($data)
    {
        return $this->createTimeSlotChoices($data);
    }
}