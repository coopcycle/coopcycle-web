<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class ArrayOfTasksInput
{
    /**
     * @var Task[]
     * @Groups({"delivery_create"})
     */
    public $tasks;
}
