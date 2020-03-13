<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class CompleteTaskInput
{
    /**
     * @var array
     * @Groups({"task_operation"})
     */
    public $data;
}
