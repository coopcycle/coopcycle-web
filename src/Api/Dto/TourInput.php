<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TourInput extends ArrayOfTasksInput
{
    /**
     * @var string
     * @Groups({"tour"})
     */
    public $name;

    /**
     * @var string
     * @Groups({"tour"})
     */
    public $date;
}

