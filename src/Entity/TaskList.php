<?php

namespace AppBundle\Entity;

use AppBundle\Action\MyTasks as MyTasksController;
use AppBundle\Action\TaskList\Create as CreateTaskListController;
use AppBundle\Action\TaskList\Optimize as OptimizeController;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Filter\DateFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * A TaskList represents the daily planning for a courier.
 * It is a concrete implementation of a TaskCollection.
 *
 * @ApiResource(
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "controller"=CreateTaskListController::class,
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     },
 *     "my_tasks"={
 *       "method"="GET",
 *       "path"="/me/tasks/{date}",
 *       "controller"=MyTasksController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "read"=false,
 *       "write"=false,
 *       "normalization_context"={"groups"={"task_collection", "task", "delivery", "address"}},
 *       "openapi_context"={
 *         "summary"="Retrieves the collection of Task resources assigned to the authenticated token.",
 *         "parameters"={{
 *           "in"="path",
 *           "name"="date",
 *           "required"=true,
 *           "type"="string",
 *           "format"="date"
 *         }}
 *       }
 *     },
 *     "optimize"={
 *        "method"="GET",
 *        "path"="/task_lists/{id}/optimize",
 *        "controller"=OptimizeController::class,
 *        "access_control"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"task_collection", "task", "address"}}
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

    /**
     * @SerializedName("date")
     * @Groups({"task_collection", "task_collections"})
     */
    public function getDateString()
    {
        return $this->date->format('Y-m-d');
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
        $task->assignTo($this->getCourier(), $this->getDate());

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

    /**
     * @SerializedName("username")
     * @Groups({"task_collection", "task_collections"})
     */
    public function getUsername()
    {
        return $this->getCourier()->getUsername();
    }
}
