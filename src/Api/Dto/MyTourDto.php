<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A Tour, as returned to the courier app inside MyTaskListDto::$items.
 *
 * Only produced when the client opts in with "?tours=1", see MyTasksProvider.
 * Without that flag the tour's tasks are flattened into the task list, which is
 * what every app version released before tours support expects.
 */
final class MyTourDto
{
    #[Groups(["task_list"])]
    public readonly int $id;

    #[Groups(["task_list"])]
    public readonly string $name;

    /**
     * @var MyTaskDto[]
     */
    #[Groups(["task_list"])]
    public readonly array $items;

    /**
     * @param MyTaskDto[] $items
     */
    public function __construct(
        int $id,
        string $name,
        array $items
    )
    {
        $this->id = $id;
        $this->name = $name;
        $this->items = $items;
    }
}
