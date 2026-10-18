<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * What to take off a task list.
 *
 * @see \AppBundle\Api\State\RemoveTaskListItemsProcessor
 */
class RemoveTaskListItemsInput
{
    /**
     * IRIs of the tasks and tours to take off the list. Anything that is not
     * on it is ignored, so asking twice is not an error.
     *
     * @var string[]
     */
    #[Groups(['task_list_items'])]
    public array $items = [];
}
