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
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_OAUTH2_TASKS')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "controller"=CreateTaskListController::class,
 *       "access_control"="is_granted('ROLE_DISPATCHER')"
 *     }
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
     * @Groups({"task_list", "task"})
     */
    protected $items;

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
     * The ordered tasks.
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

    public function addItem(Item $item) {
        $this->items->add($item);
    }

    public function containsTask(Task $task)
    {
        // TODO : check if it has still sense to do that. this function is used in EntityChangeSetProcessor and in tests
        foreach ($this->getTasks() as $t) {
            if ($task === $t) {
                return true;
            }
        }

        return false;
    }

    public function addTask(Task $task) {
        // TODO : check if this make sense. it is called from EntityChangeSetProcessor when the task is "assigned" but not in the tasklist. actually this should not happen, maybe happen from mobile dispatch (??)
        $item = new Item();
        $item->setTask($task);
        $item->setPosition($this->items->count());
        $this->items->add($item);
    }

    public function removeTask(Task $task)
    {
        foreach ($this->items as $item) {
            if ($item->getTask() === $task) {
                $this->items->removeElement($item);
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
        $this->items = $items;

        return $this;
    }
}
