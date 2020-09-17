<?php

namespace AppBundle\Utils;

// use Symfony\Component\Validator\Constraints as Assert;
// use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

use Symfony\Component\Serializer\Annotation\Groups;

class Timing
{
    /**
     * @Groups({"restaurant_timing"})
     */
    public $delivery;
    public $collection;
}
