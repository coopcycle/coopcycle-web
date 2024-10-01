<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class MyTaskList
{
    #[Groups(["task_list"])]
    public readonly int $id;

    /**
     * @var MyTask[]
     */
    #[Groups(["task_list"])]
    public readonly array $items;

    /**
     * @deprecated
     */
    public readonly bool $isTempLegacyTaskStorage;

    /**
     * @param int $id
     * @param MyTask[] $items
     */
    public function __construct(int $id, array $items)
    {
        $this->id = $id;
        $this->items = $items;

        $this->isTempLegacyTaskStorage = true;
    }
}
