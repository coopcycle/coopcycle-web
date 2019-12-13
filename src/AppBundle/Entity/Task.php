<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use AppBundle\Action\Task\Assign as TaskAssign;
use AppBundle\Action\Task\Cancel as TaskCancel;
use AppBundle\Action\Task\Done as TaskDone;
use AppBundle\Action\Task\Failed as TaskFailed;
use AppBundle\Action\Task\Unassign as TaskUnassign;
use AppBundle\Action\Task\Duplicate as TaskDuplicate;
use AppBundle\Api\Filter\AssignedFilter;
use AppBundle\Api\Filter\TaskDateFilter;
use AppBundle\Api\Filter\TaskFilter;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Validator\Constraints\Task as AssertTask;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   attributes={
 *     "denormalization_context"={"groups"={"task", "address_create", "task_edit"}},
 *     "normalization_context"={"groups"={"task", "delivery", "place"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *       "denormalization_context"={"groups"={"task", "place"}},
 *     },
 *     "my_tasks"={
 *       "method"="GET",
 *       "path"="/me/tasks/{date}",
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "pagination_enabled"=false,
 *       "filters"={},
 *       "swagger_context"={
 *         "parameters"={{
 *           "name"="date",
 *           "in"="path",
 *           "required"="true",
 *           "type"="string"
 *         }}
 *       }
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="object.isReadableBy(user)"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "denormalization_context"={"groups"={"task_edit"}}
 *     },
 *     "task_done"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/done",
 *       "controller"=TaskDone::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))"
 *     },
 *     "task_failed"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/failed",
 *       "controller"=TaskFailed::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))"
 *     },
 *     "task_assign"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/assign",
 *       "controller"=TaskAssign::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')"
 *     },
 *     "task_unassign"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/unassign",
 *       "controller"=TaskUnassign::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))"
 *     },
 *     "task_cancel"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/cancel",
 *       "controller"=TaskCancel::class,
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     },
 *     "task_duplicate"={
 *       "method"="POST",
 *       "path"="/tasks/{id}/duplicate",
 *       "controller"=TaskDuplicate::class,
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   subresourceOperations={
 *     "events_get_subresource"={
 *       "security"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))"
 *     }
 *   }
 * )
 * @AssertTask()
 * @ApiFilter(TaskDateFilter::class, properties={"date"})
 * @ApiFilter(TaskFilter::class)
 * @ApiFilter(AssignedFilter::class, properties={"assigned"})
 */
class Task implements TaggableInterface
{
    use TaggableTrait;

    const TYPE_DROPOFF = 'DROPOFF';
    const TYPE_PICKUP = 'PICKUP';

    const STATUS_TODO = 'TODO';
    const STATUS_FAILED = 'FAILED';
    const STATUS_DONE = 'DONE';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @Groups({"task", "delivery"})
     */
    private $id;

    /**
     * @Groups({"task", "task_edit"})
     */
    private $type = self::TYPE_DROPOFF;

    /**
     * @Groups({"task", "delivery"})
     */
    private $status = self::STATUS_TODO;

    private $delivery;

    /**
     * @Groups({"task", "task_edit", "place", "address_create"})
     */
    private $address;

    /**
     * @Groups({"task", "task_edit", "delivery"})
     */
    private $doneAfter;

    /**
     * @Assert\NotBlank()
     * @Assert\Expression(
     *     "this.getDoneAfter() == null or this.getDoneAfter() < this.getDoneBefore()",
     *     message="task.before.mustBeGreaterThanAfter"
     * )
     * @Groups({"task", "task_edit", "delivery"})
     */
    private $doneBefore;

    /**
     * @Groups({"task", "task_edit", "delivery"})
     */
    private $comments;

    /**
     * @ApiSubresource
     */
    private $events;

    private $createdAt;

    /**
     * @Groups({"task"})
     */
    private $updatedAt;

    private $previous;

    private $next;

    /**
     * @Groups({"task"})
     */
    private $group;

    /**
     * @Groups({"task"})
     */
    private $assignedTo;

    /**
     * @var \DateTime|null
     */
    private $assignedOn;

    /**
     * @var Collection<int,TaskImage>
     * @Groups({"task", "task_edit"})
     */
    private $images;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->images = new ArrayCollection();
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

    public function isCompleted()
    {
        return $this->isDone() || $this->isFailed();
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
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

    public function setDoneAfter(?\DateTime $doneAfter)
    {
        $this->doneAfter = $doneAfter;

        return $this;
    }

    public function getDoneBefore()
    {
        return $this->doneBefore;
    }

    public function setDoneBefore(?\DateTime $doneBefore)
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

    public function containsEventWithName($name)
    {
        foreach ($this->events as $e) {
            if ($e->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function addEvent(TaskEvent $event)
    {
        if ($event->getName() === 'task:created' && $this->containsEventWithName('task:created')) {
            return;
        }
        if ($event->getName() === 'task:done' && $this->containsEventWithName('task:done')) {
            return;
        }
        if ($event->getName() === 'task:failed' && $this->containsEventWithName('task:failed')) {
            return;
        }

        $this->events->add($event);
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

    public function getNext()
    {
        return $this->next;
    }

    public function setNext(Task $next = null)
    {
        $this->next = $next;

        return $this;
    }

    public function hasNext()
    {
        return $this->next !== null;
    }

    /**
     * @Groups({"task"})
     * @SerializedName("isAssigned")
     */
    public function isAssigned()
    {
        return null !== $this->assignedTo;
    }

    public function isAssignedTo(ApiUser $courier)
    {
        return $this->isAssigned() && $this->assignedTo === $courier;
    }

    public function getAssignedOn()
    {
        return $this->assignedOn;
    }

    public function getAssignedCourier()
    {
        return $this->assignedTo;
    }

    /**
     * @param ApiUser $courier
     * @param \DateTime|null $date
     */
    public function assignTo(ApiUser $courier, \DateTime $date = null)
    {
        if (null === $date) {
            @trigger_error('Not specifying a date when calling assignTo() is deprecated', E_USER_DEPRECATED);
        }

        $this->assignedTo = $courier;
        $this->assignedOn = $date;
    }

    public function unassign()
    {
        $this->assignedTo = null;
        $this->assignedOn = null;
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

    public function getLastEvent($name)
    {
        $criteria = Criteria::create()->orderBy(array("created_at" => Criteria::DESC));

        foreach ($this->getEvents()->matching($criteria) as $event) {
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

    public function getImages()
    {
        return $this->images;
    }

    public function setImages($images)
    {
        foreach ($images as $image) {
            $image->setTask($this);
        }

        $this->images = $images;

        return $this;
    }

    public function addImage($image)
    {
        $this->images->add($image);

        return $this;
    }

    public function duplicate()
    {
        $task = new self();

        $task->setType($this->getType());
        $task->setComments($this->getComments());
        $task->setAddress($this->getAddress());
        $task->setDoneAfter($this->getDoneAfter());
        $task->setDoneBefore($this->getDoneBefore());
        $task->setTags($this->getTags());

        return $task;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function isReadableBy(ApiUser $user)
    {
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_COURIER')) {
            return true;
        }

        if ($user->hasRole('ROLE_STORE')) {
            $delivery = $this->getDelivery();
            if (null !== $delivery) {
                $store = $delivery->getStore();
                if (null !== $store) {
                    return $user->ownsStore($store);
                }
            }
        }

        return false;
    }
}
