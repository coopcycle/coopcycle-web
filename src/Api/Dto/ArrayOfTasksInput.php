<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

class ArrayOfTasksInput
{
    /**
     * @var Task[]
     * @Groups({"tour"})
     */
    public $tasks;
}
