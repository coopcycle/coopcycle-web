<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Task\AddImagesToTasks;
use AppBundle\Action\Task\Collection as TaskList;
use AppBundle\Action\Task\Assign as TaskAssign;
use AppBundle\Action\Task\BulkAssign as TaskBulkAssign;
use AppBundle\Action\Task\Cancel as TaskCancel;
use AppBundle\Action\Task\Done as TaskDone;
use AppBundle\Action\Task\Events as TaskEvents;
use AppBundle\Action\Task\FailureReasons as TaskFailureReasons;
use AppBundle\Action\Task\Incident as TaskIncident;
use AppBundle\Action\Task\Failed as TaskFailed;
use AppBundle\Action\Task\Unassign as TaskUnassign;
use AppBundle\Action\Task\Duplicate as TaskDuplicate;
use AppBundle\Action\Task\Reschedule as TaskReschedule;
use AppBundle\Action\Task\Restore as TaskRestore;
use AppBundle\Action\Task\Start as TaskStart;
use AppBundle\Action\Task\RemoveFromGroup;
use AppBundle\Action\Task\BulkMarkAsDone as TaskBulkMarkAsDone;
use AppBundle\Action\Task\Context as TaskContext;
use AppBundle\Api\Dto\BioDeliverInput;
use AppBundle\Api\Filter\AssignedFilter;
use AppBundle\Api\Filter\TaskDateFilter;
use AppBundle\Api\Filter\TaskOrderFilter;
use AppBundle\Api\Filter\TaskFilter;
use AppBundle\Api\Filter\OrganizationFilter;
use AppBundle\DataType\TsRange;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Entity\Delivery\FailureReason;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Edifact\EDIFACTMessageAwareTrait;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Package;
use AppBundle\Entity\Package\PackagesAwareInterface;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\Package as TaskPackage;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Entity\Package\PackagesAwareTrait;
use AppBundle\ExpressionLanguage\PackagesResolver;
use AppBundle\Pricing\PriceCalculationVisitor;
use AppBundle\Pricing\PricingRuleMatcherInterface;
use AppBundle\Validator\Constraints\Task as AssertTask;
use AppBundle\Vroom\Job as VroomJob;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
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
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_COURIER')",
 *       "pagination"={
 *          "enabled_parameter_name"="pagination",
 *          "items_per_page_parameter_name"="itemsPerPage"
 *         },
 *       "pagination_client_items_per_page"=true,
 *       "pagination_enabled"=false,
 *       "pagination_client_enabled"=true,
 *       "controller"=TaskList::class
 *     },
 *     "post"={
 *       "method"="POST",
 *       "access_control"="is_granted('ROLE_DISPATCHER')",
 *       "denormalization_context"={"groups"={"task_create"}},
 *       "validation_groups"={"Default"}
 *     },
 *     "tasks_assign"={
 *       "method"="PUT",
 *       "path"="/tasks/assign",
 *       "controller"=TaskBulkAssign::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "openapi_context"={
 *         "summary"="Assigns multiple Tasks at once to a messenger",
 *         "parameters"={
 *           {
 *             "in"="body",
 *             "name"="N/A",
 *             "schema"={"type"="object", "properties"={"username"={"type"="string"}, "tasks"={"type"="array"}}},
 *             "style"="form"
 *           }
 *         }
 *       }
 *     },
 *     "tasks_done"={
 *       "method"="PUT",
 *       "path"="/tasks/done",
 *       "controller"=TaskBulkMarkAsDone::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "openapi_context"={
 *         "summary"="Mark multiple Tasks as done at once",
 *         "parameters"={
 *           {
 *             "in"="body",
 *             "name"="N/A",
 *             "schema"={"type"="object", "properties"={"tasks"={"type"="array"}}},
 *             "style"="form"
 *           }
 *         }
 *       }
 *     },
 *     "tasks_images"={
 *       "method"="PUT",
 *       "path"="/tasks/images",
 *       "denormalization_context"={"groups"={"tasks_images"}},
 *       "controller"=AddImagesToTasks::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *       "openapi_context"={
 *         "summary"="",
 *         "parameters"={
 *           {
 *             "in"="body",
 *             "name"="N/A",
 *             "schema"={"type"="object", "properties"={"tasks"={"type"="array"}, "images"={"type"="array"}}},
 *             "style"="form"
 *           }
 *         }
 *       }
 *     },
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('view', object)"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "denormalization_context"={"groups"={"task_edit"}}
 *     },
 *     "task_start"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/start",
 *       "controller"=TaskStart::class,
 *       "access_control"="is_granted('ROLE_DISPATCHER') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "openapi_context"={
 *         "summary"="Marks a Task as started"
 *       }
 *     },
 *     "task_done"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/done",
 *       "controller"=TaskDone::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_DISPATCHER') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
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
 *       "access_control"="is_granted('ROLE_DISPATCHER') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
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
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_COURIER')",
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
 *     "task_remove_from_group"={
 *       "method"="DELETE",
 *       "path"="/tasks/{id}/group",
 *       "controller"=RemoveFromGroup::class,
 *       "write"=false,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Remove a task from the group to which it belongs",
 *        }
 *     },
 *     "task_unassign"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/unassign",
 *       "controller"=TaskUnassign::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_DISPATCHER') or (is_granted('ROLE_COURIER') and object.isAssignedTo(user))",
 *       "openapi_context"={
 *         "summary"="Unassigns a Task from a messenger"
 *       }
 *     },
 *     "task_cancel"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/cancel",
 *       "controller"=TaskCancel::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_DISPATCHER')",
 *       "openapi_context"={
 *         "summary"="Cancels a Task"
 *       }
 *     },
 *     "task_duplicate"={
 *       "method"="POST",
 *       "path"="/tasks/{id}/duplicate",
 *       "controller"=TaskDuplicate::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_DISPATCHER')",
 *       "openapi_context"={
 *         "summary"="Duplicates a Task"
 *       }
 *     },
 *     "task_reschedule"={
 *        "method"="PUT",
 *        "path"="/tasks/{id}/reschedule",
 *        "controller"=TaskReschedule::class,
 *        "denormalization_context"={"groups"={"task_operation"}},
 *        "access_control"="is_granted('ROLE_DISPATCHER')",
 *        "openapi_context"={
 *          "summary"="Reschedules a Task",
 *          "parameters"={
 *            {
 *              "in"="body",
 *              "name"="N/A",
 *              "schema"={"type"="object", "properties"={
 *                  "after"={"type"="string","format"="date-time"},
 *                  "before"={"type"="string","format"="date-time"},
 *              }},
 *              "style"="form"
 *            }
 *          }
 *        }
 *      },
 *      "task_failure_reasons"={
 *        "method"="GET",
 *        "path"="/tasks/{id}/failure_reasons",
 *        "controller"=TaskFailureReasons::class,
 *        "security"="is_granted('view', object)",
 *        "openapi_context"={
 *          "summary"="Retrieves possible failure reasons for a Task"
 *        }
 *      },
 *     "task_incident"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/incidents",
 *       "controller"=TaskIncident::class,
 *       "security"="is_granted('view', object)",
 *       "openapi_context"={
 *         "summary"="Creates an incident for a Task"
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
 *     },
 *     "task_restore"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/restore",
 *       "controller"=TaskRestore::class,
 *       "denormalization_context"={"groups"={"task_operation"}},
 *       "access_control"="is_granted('ROLE_DISPATCHER')",
 *       "openapi_context"={
 *         "summary"="Restores a Task"
 *       }
 *     },
 *     "put_bio_deliver"={
 *       "method"="PUT",
 *       "path"="/tasks/{id}/bio_deliver",
 *       "security"="is_granted('ROLE_OAUTH2_TASKS')",
 *       "input"=BioDeliverInput::class,
 *       "denormalization_context"={"groups"={"task_edit"}}
 *     },
 *     "get_task_context"={
 *       "method"="GET",
 *       "path"="/tasks/{id}/context",
 *       "controller"=TaskContext::class,
 *       "security"="is_granted('view', object)"
 *     }
 *   }
 * )
 * @AssertTask()
 * @ApiFilter(TaskOrderFilter::class)
 * @ApiFilter(TaskDateFilter::class, properties={"date"})
 * @ApiFilter(TaskFilter::class)
 * @ApiFilter(AssignedFilter::class, properties={"assigned"})
 * @ApiFilter(OrganizationFilter::class, properties={"organization"})
 * @UniqueEntity(fields={"organization", "ref"}, errorPath="ref")
 */
class Task implements TaggableInterface, OrganizationAwareInterface, PackagesAwareInterface, PricingRuleMatcherInterface
{
    use TaggableTrait;
    use OrganizationAwareTrait;

    /**
     * We actually don't expose the 'packages' property in the API, we use aggregates :
     * - DROPOFF : all packages aggregated by package name
     * - PICKUP : all packages of the delivery aggregated by package name
    */
    use PackagesAwareTrait;
    use EDIFACTMessageAwareTrait;

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
     * @Groups({"task", "task_create", "task_edit", "delivery_create"})
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
     * @Groups({"task", "task_create", "task_edit", "address", "address_create", "delivery_create"})
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

    /**
     * @Groups({"task", "delivery"})
     */
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

    /**
     * @var array
     * @Groups({"task", "task_edit"})
     */
    private $metadata = [];

    /**
     *
     * We actually don't expose the 'weight' property in the API, we expose :
     * - DROPOFF : weight property
     * - PICKUP : sum of weight of all the dropoffs
     * @var int
     * @Groups({"task", "task_create", "task_edit", "delivery", "delivery_create", "pricing_deliveries"})
     */
    private $weight;

    /**
    * @Groups({"task"})
    */
    private $incidents;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->packages = new ArrayCollection();
        $this->edifactMessages = new ArrayCollection();
        $this->incidents = new ArrayCollection();
    }

    /**
    * Non-DB-mapped property to store packages and weight aggregates (see on $weight and $packages property for aggregates definitions)
    * // FIXME : make annotation works with PHPStan
    * ['weight' => int|null, 'packages' => ['name' => string, 'type' => string, 'quantity' => int]|null]
    */
    private $prefetchedPackagesAndWeight;


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

    public function addImages($images)
    {
        foreach ($images as $image) {
            $this->addImage($image);
            $image->setTask($this);
        }

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

    /**
     * @deprecated
     * @return mixed
     */
    public function getDoneAfter()
    {
        return $this->getAfter();
    }

    /**
     * @deprecated
     * @param \DateTime|null $after
     * @return $this
     */
    public function setDoneAfter(?\DateTime $after)
    {
        return $this->setAfter($after);
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function getDoneBefore()
    {
        return $this->getBefore();
    }

    /**
     * @deprecated
     * @param \DateTime|null $before
     * @return $this
     */
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

    public static function toVroomJob(Task $task, $iri): VroomJob
    {
        $job = new VroomJob();

        $job->id = $task->getId();
        $job->description = $iri; // if the task is linked to a tour, this will be the tour iri, otherwise the task iri
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

    public function setMetadata($key)
    {
        if (func_num_args() === 1 && is_array(func_get_arg(0))) {
            $this->metadata = func_get_arg(0);
        } elseif (func_num_args() === 2) {
            $this->metadata[func_get_arg(0)] = func_get_arg(1);
        }
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getImportedFrom(): ?string {
        return collect($this->getMetadata())->get('imported_from');
    }

    public function setImportedFrom(?string $importedFrom): self
    {
        // Not updating metadata, read-only property
        return $this;
    }

    public function getPackages()
    {
        return $this->packages;
    }

    public function addPackageWithQuantity(Package $package, $quantity = 1)
    {
        if (0 === $quantity) {
            return;
        }

        $wrappedPackage = $this->resolvePackage($package);
        $wrappedPackage->setQuantity($wrappedPackage->getQuantity() + $quantity);

        if (!$this->packages->contains($wrappedPackage)) {
            $this->packages->add($wrappedPackage);
        }
    }

    public function setQuantityForPackage(Package $package, $quantity = 1)
    {
        $wrappedPackage = $this->resolvePackage($package);

        if (0 === $quantity) {
            if ($this->packages->contains($wrappedPackage)) {
                $this->packages->removeElement($wrappedPackage);
            }
            return;
        }

        $wrappedPackage->setQuantity($quantity);

        if (!$this->packages->contains($wrappedPackage)) {
            $this->packages->add($wrappedPackage);
        }
    }

    public function removePackage(Package $package)
    {
        $wrappedPackage = $this->resolvePackage($package);

        if ($this->packages->contains($wrappedPackage)) {
            $this->packages->removeElement($wrappedPackage);
            $wrappedPackage->setTask(null);
        }
    }

    protected function resolvePackage(Package $package): TaskPackage
    {
        if ($this->hasPackage($package)) {
            foreach ($this->packages as $taskPackage) {
                if ($taskPackage->getPackage() === $package) {
                    return $taskPackage;
                }
            }
        }

        $taskPackage = new TaskPackage($this);
        $taskPackage->setPackage($package);

        return $taskPackage;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    public function addToStore(Store $store)
    {
        $this->setOrganization($store->getOrganization());
    }

    public function toExpressionLanguageObject()
    {
        $taskObject = new \stdClass();

        $taskObject->type = $this->getType();
        $taskObject->address = $this->getAddress();
        $taskObject->createdAt = $this->getCreatedAt();
        $taskObject->after = $this->getAfter();
        $taskObject->before = $this->getBefore();
        $taskObject->doorstep = $this->isDoorstep();

        return $taskObject;
    }

    public function toExpressionLanguageValues()
    {
        $values = Delivery::toExpressionLanguageValues($this->getDelivery());

        $emptyObject = new \stdClass();
        $emptyObject->address = null;
        $emptyObject->createdAt = null;
        $emptyObject->after = null;
        $emptyObject->before = null;
        $emptyObject->doorstep = false;

        $thisObj = $this->toExpressionLanguageObject();

        $values['distance'] = -1;
        $values['weight'] = $this->getWeight();
        $values['pickup'] = $this->isPickup() ? $thisObj : $emptyObject;
        $values['dropoff'] = $this->isDropoff() ? $thisObj : $emptyObject;
        $values['packages'] = new PackagesResolver($this);

        $values['task'] = $thisObj;

        return $values;
    }

    public function matchesPricingRule(PricingRule $pricingRule, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $expression = $pricingRule->getExpression();

        return $language->evaluate($expression, $this->toExpressionLanguageValues());
    }

    public function appendToComments($comments)
    {
        $this->comments = ($this->comments ?? '') . "\n\n" . $comments;
    }

    public function evaluatePrice(PricingRule $pricingRule, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        return $language->evaluate($pricingRule->getPrice(), $this->toExpressionLanguageValues());
    }

    /**
     * @Groups({"task"})
     */
    public function getHasIncidents(): bool
    {
        return !$this->getIncidents()->filter(function (Incident $incident) {
            return $incident->getStatus() === Incident::STATUS_OPEN;
        })->isEmpty();
    }

    public function getIncidents(): Collection
    {
        return $this->incidents;
    }

    public function addIncident(Incident $incident): void
    {
        $this->incidents[] = $incident;
    }

    /**
     * Get the value of prefetchedPackagesAndWeight
     */
    public function getPrefetchedPackagesAndWeight()
    {
        return $this->prefetchedPackagesAndWeight;
    }

    /**
     * Set the value of prefetchedPackagesAndWeight
     *
     * @return  self
     */
    public function setPrefetchedPackagesAndWeight($prefetchedPackagesAndWeight)
    {
        $this->prefetchedPackagesAndWeight = $prefetchedPackagesAndWeight;

        return $this;
    }

    public function acceptPriceCalculationVisitor(PriceCalculationVisitor $visitor)
    {
        $visitor->visitTask($this);
    }
}
