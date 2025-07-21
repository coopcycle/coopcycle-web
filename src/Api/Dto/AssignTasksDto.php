<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

class AssignTasksDto
{
    #[Groups(['tasks_assign'])]
    public string $username;

    /**
     * @var Task[]
     */
    #[Groups(['tasks_assign'])]
    public $tasks = [];
}

