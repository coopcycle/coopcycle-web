<?php

namespace AppBundle\Entity;

use AppBundle\Action\MyTasks as MyTasksController;
use AppBundle\Action\TaskList\Create as CreateTaskListController;
use AppBundle\Action\TaskList\Optimize as OptimizeController;
use AppBundle\Action\TaskList\SetItems as SetTaskListItemsController;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Filter\DateFilter;
use AppBundle\Entity\Task\CollectionTrait as TaskCollectionTrait;
use AppBundle\Entity\TaskList\Item;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * A TaskList represents the daily planning for a courier.
 * It is a concrete implementation of a TaskCollection.
 *
 * @ApiResource(
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_OAUTH2_TASKS')",
 *       "openapi_context"={
 *         "summary"="Legacy endpoint, please use '/api/task_lists/v2' instead. Retrieves Tasklists as lists of tasks, not tasks and tours, with expanded tasks."
 *       },
 *       "normalization_context"={"groups"={"task_list", "task_collection", "task", "delivery", "address"}}
 *     },
 *     "post"={
 *       "method"="POST",
 *       "controller"=CreateTaskListController::class,
 *       "access_control"="is_granted('ROLE_DISPATCHER')"
 *     },
 *     "v2"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_OAUTH2_TASKS')",
 *       "path"="/task_lists/v2",
 *       "openapi_context"={
 *         "summary"="Retrieves TaskLists as lists of tours and tasks."
 *       }
 *    }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER')"
 *     },
 *     "set_items"={
 *       "method"="PUT",
 *       "access_control"="is_granted('ROLE_DISPATCHER')",
 *       "path"="/task_lists/set_items/{date}/{username}",
 *       "controller"=SetTaskListItemsController::class,
 *       "read"=false,
 *       "write"=false
 *      },
 *     "my_tasks"={
 *       "method"="GET",
 *       "path"="/me/tasks/{date}",
 *       "controller"=MyTasksController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "read"=false,
 *       "write"=false,
 *       "normalization_context"={"groups"={"task_list", "task", "delivery", "address"}},
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
 *     "normalization_context"={"groups"={"task_list"}}
 *   }
 * )
 * @ApiFilter(DateFilter::class, properties={"date"})
 */
class TaskList implements TaskCollectionInterface
{
    use TaskCollectionTrait;

    private $id;

    /**
     * @Assert\Valid()
     * @Groups({"task_list"})
     */
    protected $items;

    /**
     * Legacy props to filter then display a TaskList as a list of tasks
     * Can be get and set, but not persisted to the database
     * @deprecated
     */
    protected $tempLegacyTaskStorage;

    private $date;

    private $courier;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDate()
    {
        return $this->date;
    }

    /**
     * @SerializedName("date")
     * @Groups({"task_list"})
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
     * The ordered tasks of the TaskList. Is useful for calculating distances or operations on tasks, but for dispatch read/write you would prefer `getItems` as it will return the tasklist as a list of both tasks and tours.
     *
     * @return Task[]
     */
    public function getTasks() {
        $items = $this->getItems();
        $tasks = [];

        foreach($items as $item) {
            if (is_null($item->getTask())) {
                $tasks = array_merge($tasks, $item->getTour()->getTasks());
            } else {
                array_push($tasks, $item->getTask());
            }
        }
        return $tasks;
    }

    public function containsTask(Task $task)
    {
        foreach ($this->getTasks() as $t) {
            if ($task === $t) {
                return true;
            }
        }

        return false;
    }

    /**
     * Only used in `EntityChangeSetProcessor` class to sync $task state into $tasklist.
     * Should not generally not be used; please use TaskListManager->assign
     */
    public function removeTask(Task $task)
    {
        foreach ($this->items as $item) {
            if ($item->getTask() === $task) {
                $this->items->removeElement($item);
                $item->setParent(null);
                break;
            }
        }
    }

    /**
     * @SerializedName("username")
     * @Groups({"task_list"})
     */
    public function getUsername()
    {
        return $this->getCourier()->getUsername();
    }

    /**
     * Get the value of items
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the value of items
     *
     * @return  self
     */
    public function setItems($items)
    {
        foreach($items as $item) {
            $this->items->add($item);
            $item->setParent($this);
        }

        return $this;
    }

    public function addItem(Item $item) {
        $this->items->add($item);
        $item->setParent($this);
    }

     /**
     * Clear the assigned items
     */
    public function clear()
    {
        foreach($this->items as $item) {
            $item->setParent(null);
        }
        return $this->items->clear();
    }

    /**
     * Get legacy props to manage a TaskList as a list of tasks
     */
    public function getTempLegacyTaskStorage()
    {
        return $this->tempLegacyTaskStorage;
    }

    /**
     * Set legacy props to manage a TaskList as a list of tasks
     *
     * @return  self
     */
    public function setTempLegacyTaskStorage($tempLegacyTaskStorage)
    {
        $this->tempLegacyTaskStorage = $tempLegacyTaskStorage;

        return $this;
    }
}
