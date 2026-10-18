<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * What to put on a task list, as a delta.
 *
 * @see \AppBundle\Api\State\AddTaskListItemsProcessor
 */
class AddTaskListItemsInput
{
    /**
     * IRIs of the tasks and tours to put on the list. Items already there are
     * moved, not duplicated; everything not named is left alone.
     *
     * @var string[]
     */
    #[Groups(['task_list_items'])]
    public array $items = [];

    /**
     * Where to insert them. Appended when absent, which is what a client that
     * does not care about ordering should send.
     */
    #[Groups(['task_list_items'])]
    public ?int $position = null;
}
