<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A TaskList represents the daily planning for a courier.
 * It is a concrete implementation of a TaskCollection.
 *
 * @ApiResource(
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"},
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"task_collection", "task"}}
 *   }
 * )
 */
class TaskList extends TaskCollection implements TaskCollectionInterface
{
    private $date;

    private $courier;

    public function getDate()
    {
        return $this->date;
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;

        return $this;
    }

    public function getCourier()
    {
        return $this->courier;
    }

    public function setCourier($courier)
    {
        $this->courier = $courier;

        return $this;
    }

    /**
     * When a Task is added, it is assigned.
     */
    public function addTask(Task $task, $position = null)
    {
        $task->assignTo($this->getCourier());

        return parent::addTask($task, $position);
    }

    /**
     * When a Task is removed, it is unassigned.
     */
    public function removeTask(Task $task)
    {
        $task->unassign();

        return parent::removeTask($task);
    }
}
