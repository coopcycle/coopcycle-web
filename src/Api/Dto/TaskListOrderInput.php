<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The order a task list should be in.
 *
 * @see \AppBundle\Api\State\SetTaskListOrderProcessor
 */
class TaskListOrderInput
{
    /**
     * Every item currently on the list, in the wanted order, as task and tour
     * IRIs. Naming anything else, or leaving anything out, is refused: this
     * operation reorders, it does not assign.
     *
     * @var string[]
     */
    #[Groups(['task_list_order'])]
    public array $items = [];
}
