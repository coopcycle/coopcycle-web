<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class CancelTasksDto
{
    /**
     * Kept as IRIs, so that a task that can't be found is reported as failed,
     * instead of making the whole request fail
     *
     * @var string[]
     */
    #[Groups(['tasks_cancel'])]
    public array $tasks = [];
}
