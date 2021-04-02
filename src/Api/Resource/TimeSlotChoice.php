<?php

namespace AppBundle\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

final class TimeSlotChoice
{
    /**
     * @var string
     *
     * @Groups({"time_slot_choices"})
     */
    public $value;

    /**
     * @var string
     */
    public $label;
}
