<?php

namespace AppBundle\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

final class TimeSlotChoices
{
    /**
     * @var array
     *
     * @Groups({"time_slot_choices"})
     */
    public $choices = [];
}
