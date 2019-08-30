<?php

namespace AppBundle\Entity;

use AppBundle\Action\TaskList\Create as CreateTaskListController;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Filter\DateFilter;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A TaskList represents the daily planning for a courier.
 * It is a concrete implementation of a TaskCollection.
 *
 * @ApiResource(
 *   collectionOperations={
 *     "my_tasks" = {
 *       "route_name" = "my_tasks",
 *       "swagger_context" = {
 *         "parameters" = {{
 *           "name" = "date",
 *           "in" = "path",
 *           "required" = "true",
 *           "type" = "string"
 *         }}
 *       }
 *     },
 *     "get"={"method"="GET"},
 *     "post"={
 *       "method"="POST",
 *       "controller"=CreateTaskListController::class,
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"task_collection", "task", "place"}}
 *   }
 * )
 * @ApiFilter(DateFilter::class, properties={"date"})
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
    public function removeTask(Task $task, $unassign = true)
    {
        if ($unassign) {
            $task->unassign();
        }

        return parent::removeTask($task);
    }
}
