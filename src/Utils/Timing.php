<?php

namespace AppBundle\Utils;

use Symfony\Component\Serializer\Annotation\Groups;

class Timing
{
    /**
     * @Groups({"restaurant_timing"})
     */
    public $delivery;

    /**
     * @Groups({"restaurant_timing"})
     */
    public $collection;
}
