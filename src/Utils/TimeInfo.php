<?php

namespace AppBundle\Utils;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

class TimeInfo
{
    /**
     * @Groups({"restaurant_timing"})
     */
    public $range;

    /**
     * @Groups({"restaurant_timing"})
     */
    public $today;

    /**
     * @Groups({"restaurant_timing"})
     */
    public $fast;

    /**
     * @Groups({"restaurant_timing"})
     */
    public $diff;
}
