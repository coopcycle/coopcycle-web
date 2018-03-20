<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *   attributes={
 *     "denormalization_context"={"groups"={"task"}},
 *     "normalization_context"={"groups"={"task", "delivery", "place"}}
 *   },
 *   collectionOperations={
 *     "get"={"method"="GET"},
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
 *     }
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "task_done"={"route_name"="api_task_done"},
 *     "task_failed"={"route_name"="api_task_failed"}
 *   }
 * )
 */
class Task implements TaggableInterface
{
    use TaggableTrait;

    const TYPE_DROPOFF = 'DROPOFF';
    const TYPE_PICKUP = 'PICKUP';

    const STATUS_TODO = 'TODO';
    const STATUS_FAILED = 'FAILED';
    const STATUS_DONE = 'DONE';

    /**
     * @Groups({"task"})
     */
    private $id;

    /**
     * @Groups({"task"})
     */
    private $type = self::TYPE_DROPOFF;

    /**
     * @Groups({"task"})
     */
    private $status = self::STATUS_TODO;

    private $delivery;

    /**
     * @Groups({"task"})
     */
    private $address;

    /**
     * @Groups({"task"})
     */
    private $doneAfter;

    /**
     * @Groups({"task"})
     */
    private $doneBefore;

    /**
     * @Groups({"task"})
     */
    private $comments;

    /**
     * @Groups({"task"})
     */
    private $events;

    /**
     * @Groups({"task"})
     */
    private $createdAt;

    /**
     * @Groups({"task"})
     */
    private $updatedAt;

    private $previous;

    private $group;

    /**
     * @Groups({"task"})
     */
    private $assignedTo;

    public function __construct()
    {
        $this->events = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function isPickup()
    {
        return $this->type === self::TYPE_PICKUP;
    }

    public function isDropoff()
    {
        return $this->type === self::TYPE_DROPOFF;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function isDone()
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    public function getDoneAfter()
    {
        return $this->doneAfter;
    }

    public function setDoneAfter(\DateTime $doneAfter = null)
    {
        $this->doneAfter = $doneAfter;

        return $this;
    }

    public function getDoneBefore()
    {
        return $this->doneBefore;
    }

    public function setDoneBefore(\DateTime $doneBefore)
    {
        $this->doneBefore = $doneBefore;

        return $this;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getEvents()
    {
        return $this->events;
    }

    public function getPrevious()
    {
        return $this->previous;
    }

    public function setPrevious(Task $previous = null)
    {
        $this->previous = $previous;

        return $this;
    }

    public function hasPrevious()
    {
        return $this->previous !== null;
    }

    public function isAssigned()
    {
        return null !== $this->assignedTo;
    }

    public function isAssignedTo(ApiUser $courier)
    {
        return $this->isAssigned() && $this->assignedTo === $courier;
    }

    public function getAssignedCourier()
    {
        return $this->assignedTo;
    }

    public function assignTo(ApiUser $courier)
    {
        $this->assignedTo = $courier;
    }

    public function unassign()
    {
        $this->assignedTo = null;
    }

    public function hasEvent($name)
    {
        foreach ($this->getEvents() as $event) {
            if ($event->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function getFirstEvent($name)
    {
        foreach ($this->getEvents() as $event) {
            if ($event->getName() === $name) {
                return $event;
            }
        }
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function setGroup(TaskGroup $group = null)
    {
        $this->group = $group;

        return $this;
    }
}
