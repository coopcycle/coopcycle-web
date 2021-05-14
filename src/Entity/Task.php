<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Task\Assign as TaskAssign;
use AppBundle\Action\Task\Cancel as TaskCancel;
use AppBundle\Action\Task\Done as TaskDone;
use AppBundle\Action\Task\Events as TaskEvents;
use AppBundle\Action\Task\Failed as TaskFailed;
use AppBundle\Action\Task\Unassign as TaskUnassign;
use AppBundle\Action\Task\Duplicate as TaskDuplicate;
use AppBundle\Action\Task\Start as TaskStart;
use AppBundle\Api\Filter\AssignedFilter;
use AppBundle\Api\Filter\TaskDateFilter;
use AppBundle\Api\Filter\TaskFilter;
use AppBundle\DataType\TsRange;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Validator\Constraints\Task as AssertTask;
use AppBundle\Vroom\Job as VroomJob;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"task", "delivery", "address"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "pagination_enabled"=false
 *     },
 *     "post"={
 *       "method"="POST",
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *       "denormalization_context"={"groups"={"task_create"}},
 *       "validation_groups"={"Default"}
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('view', object)"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "denormalization_context"={"groups"={"task_edit"}}
 *     },
 *     "task_start"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/start",
 *       "controller"=TaskStart::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "openapi_context"={
 *         "summary"="Marks a Task as started"
 *       }
 *     },
 *     "task_done"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/done",
 *       "controller"=TaskDone::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "openapi_context"={
 *         "summary"="Marks a Task as done",
 *         "parameters"={
 *           {
 *             "in"="body",
 *             "name"="N/A",
 *             "schema"={"type"="object", "properties"={"notes"={"type"="string"}}},
 *             "style"="form"
 *           }
 *         }
 *       }
 *     },
 *     "task_failed"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/failed",
 *       "controller"=TaskFailed::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "openapi_context"={
 *         "summary"="Marks a Task as failed",
 *         "parameters"={
 *           {
 *             "in"="body",
 *             "name"="N/A",
 *             "schema"={"type"="object", "properties"={"notes"={"type"="string"}}},
 *             "style"="form"
 *           }
 *         }
 *       }
 *     },
 *     "task_assign"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/assign",
 *       "controller"=TaskAssign::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "openapi_context"={
 *         "summary"="Assigns a Task to a messenger",
 *         "parameters"={
 *           {
 *             "in"="body",
 *             "name"="N/A",
 *             "schema"={"type"="object", "properties"={"username"={"type"="string"}}},
 *             "style"="form"
 *           }
 *         }
 *       }
 *     },
 *     "task_unassign"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/unassign",
 *       "controller"=TaskUnassign::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "openapi_context"={
 *         "summary"="Unassigns a Task from a messenger"
 *       }
 *     },
 *     "task_cancel"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/cancel",
 *       "controller"=TaskCancel::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *       "openapi_context"={
 *         "summary"="Cancels a Task"
 *       }
 *     },
 *     "task_duplicate"={
 *       "method"="POST",
 *       "path"="/tasks/{id}/duplicate",
 *       "controller"=TaskDuplicate::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *       "openapi_context"={
 *         "summary"="Duplicates a Task"
 *       }
 *     },
 *     "task_events"={
 *       "method"="GET",
 *       "path"="/tasks/{id}/events",
 *       "controller"=TaskEvents::class,
 *       "security"="is_granted('view', object)",
 *       "openapi_context"={
 *         "summary"="Retrieves events for a Task"
 *       }
 *     }
 *   }
 * )
 * @AssertTask()
 * @ApiFilter(TaskDateFilter::class, properties={"date"})
 * @ApiFilter(TaskFilter::class)
 * @ApiFilter(AssignedFilter::class, properties={"assigned"})
 * @UniqueEntity(fields={"organization", "ref"}, errorPath="ref")
 */
class Task implements TaggableInterface, OrganizationAwareInterface
{
    use TaggableTrait;
    use OrganizationAwareTrait;

    const TYPE_DROPOFF = 'DROPOFF';
    const TYPE_PICKUP = 'PICKUP';

    const STATUS_TODO = 'TODO';
    const STATUS_DOING = 'DOING';
    const STATUS_FAILED = 'FAILED';
    const STATUS_DONE = 'DONE';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * The radius (in meters) that is used for geofences.
     * @var int
     */
    const GEOFENCING_RADIUS = 300;

    /**
     * @Groups({"task", "delivery"})
     */
    private $id;

    /**
     * @Assert\Choice({"PICKUP", "DROPOFF"})
     * @Groups({"task", "task_create", "task_edit"})
     */
    private $type = self::TYPE_DROPOFF;

    /**
     * @Groups({"task", "delivery"})
     */
    private $status = self::STATUS_TODO;

    private $delivery;

    /**
     * @Assert\NotNull()
     * @Assert\Valid()
     * @Groups({"task", "task_create", "task_edit", "address", "address_create", "delivery_create", "pricing_rule_evalute"})
     */
    private $address;

    private $doneAfter;

    /**
     * @Assert\NotBlank()
     * @Assert\Expression(
     *     "this.getDoneAfter() == null or this.getDoneAfter() < this.getDoneBefore()",
     *     message="task.before.mustBeGreaterThanAfter"
     * )
     */
    private $doneBefore;

    /**
     * @Groups({"task", "task_create", "task_edit", "delivery", "delivery_create"})
     */
    private $comments;

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
     * @Groups({"task_edit"})
     */
    private $images;

    /**
     * @var int
     */
    private $imageCount = 0;

    /**
     * @Groups({"task", "task_create", "task_edit"})
     */
    private $doorstep = false;

    /**
     * @Groups({"task", "task_create"})
     */
    private $ref;

    /**
     * @var RecurrenceRule|null
     * @Groups({"task"})
     */
    private $recurrenceRule;

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

    /**
     * @SerializedName("after")
     * @Groups({"task", "task_edit", "delivery"})
     */
    public function getAfter()
    {
        return $this->doneAfter;
    }

    /**
     * @SerializedName("after")
     * @Groups({"task", "task_create", "task_edit", "delivery", "delivery_create"})
     */
    public function setAfter(?\DateTime $doneAfter)
    {
        $this->doneAfter = $doneAfter;

        return $this;
    }

    /**
     * @SerializedName("before")
     * @Groups({"task", "task_create", "task_edit", "delivery", "delivery_create"})
     */
    public function getBefore()
    {
        return $this->doneBefore;
    }

    /**
     * @SerializedName("before")
     * @Groups({"task", "task_edit", "delivery"})
     */
    public function setBefore(?\DateTime $doneBefore)
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
        $iterator = $this->events->getIterator();
        $iterator->uasort(function (TaskEvent $a, TaskEvent $b) {
            return $a->getCreatedAt() < $b->getCreatedAt() ? -1 : 1;
        });

        return new ArrayCollection(
            iterator_to_array($iterator)
        );
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

    public function isAssignedTo(User $courier)
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
     * @param User $courier
     * @param \DateTime|null $date
     */
    public function assignTo(User $courier, \DateTime $date = null)
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
        $criteria = Criteria::create()->orderBy(array('created_at' => Criteria::DESC));

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
        $this->imageCount = count($this->images);

        return $this;
    }

    public function addImage($image)
    {
        $this->images->add($image);
        $this->imageCount = count($this->images);

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

    public function getCompletedAt()
    {
        if ($this->hasEvent(TaskDomainEvent\TaskDone::messageName())) {
            return $this->getLastEvent(TaskDomainEvent\TaskDone::messageName())->getCreatedAt();
        }

        if ($this->hasEvent(TaskDomainEvent\TaskFailed::messageName())) {
            return $this->getLastEvent(TaskDomainEvent\TaskFailed::messageName())->getCreatedAt();
        }
    }

    public function getTimeRange(): TsRange
    {
        $range = new TsRange();

        $range->setLower($this->getAfter());
        $range->setUpper($this->getBefore());

        return $range;
    }

    /* Legacy */

    public function getDoneAfter()
    {
        return $this->getAfter();
    }

    public function setDoneAfter(?\DateTime $after)
    {
        return $this->setAfter($after);
    }

    public function getDoneBefore()
    {
        return $this->getBefore();
    }

    public function setDoneBefore(?\DateTime $before)
    {
        return $this->setBefore($before);
    }

    public function setDoorstep($doorstep)
    {
        $this->doorstep = $doorstep;

        return $this;
    }

    public function isDoorstep()
    {
        return $this->doorstep;
    }

    /**
     * @SerializedName("orgName")
     * @Groups({"task"})
     */
    public function getOrganizationName()
    {
        $organization = $this->getOrganization();

        if ($organization) {

            return $organization->getName();
        }

        return '';
    }

    public function setRef(string $ref)
    {
        $this->ref = $ref;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public static function toVroomJob(Task $task): VroomJob
    {
        $job = new VroomJob();

        $job->id = $task->getId();
        $job->location = [
            $task->getAddress()->getGeo()->getLongitude(),
            $task->getAddress()->getGeo()->getLatitude()
        ];
        $job->time_windows = [
            [
                (int) $task->getAfter()->format('U'),
                (int) $task->getBefore()->format('U')
            ]
        ];

        return $job;
    }

    public function setRecurrenceRule(RecurrenceRule $recurrenceRule)
    {
        $this->recurrenceRule = $recurrenceRule;

        return $this;
    }

    public function getRecurrenceRule(): ?RecurrenceRule
    {
        return $this->recurrenceRule;
    }

    /**
     * @SerializedName("images")
     * @Groups({"task"})
     */
    public function getImagesWithCache()
    {
        if (0 === $this->imageCount) {
            return new ArrayCollection();
        }

        return $this->getImages();
    }
}
