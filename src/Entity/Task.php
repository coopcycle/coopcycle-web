<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Task\AddImagesToTasks;
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
use AppBundle\Action\Task\AppendToComment as TaskAppendToComment;
use AppBundle\Api\Dto\BioDeliverInput;
use AppBundle\Api\Filter\AssignedFilter;
use AppBundle\Api\Filter\TaskDateFilter;
use AppBundle\Api\Filter\TaskOrderFilter;
use AppBundle\Api\Filter\TaskFilter;
use AppBundle\Api\Filter\OrganizationFilter;
use AppBundle\Api\State\BioDeliverProcessor;
use AppBundle\DataType\TsRange;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Entity\Delivery\FailureReason;
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
use AppBundle\Entity\TimeSlot\TimeSlotAwareInterface;
use AppBundle\Utils\Barcode\Barcode;
use AppBundle\Utils\Barcode\BarcodeUtils;
use AppBundle\Validator\Constraints\Task as AssertTask;
use AppBundle\Vroom\Job as VroomJob;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'view\', object)'),
        new Put(
            security: 'is_granted(\'ROLE_DISPATCHER\') or (is_granted(\'ROLE_COURIER\') and object.isAssignedTo(user))',
            denormalizationContext: ['groups' => ['task_edit']],
            // Make sure to add requirements for operations like "/tasks/assign" to work
            requirements: ['id' => '[0-9]+']
        ),
        new Put(uriTemplate: '/tasks/{id}/start', controller: TaskStart::class, security: 'is_granted(\'ROLE_DISPATCHER\') or (is_granted(\'ROLE_COURIER\') and object.isAssignedTo(user))', openapiContext: ['summary' => 'Marks a Task as started']),
        new Put(uriTemplate: '/tasks/{id}/done', controller: TaskDone::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\') or (is_granted(\'ROLE_COURIER\') and object.isAssignedTo(user))', openapiContext: ['summary' => 'Marks a Task as done', 'parameters' => [['in' => 'body', 'name' => 'N/A', 'schema' => ['type' => 'object', 'properties' => ['notes' => ['type' => 'string']]], 'style' => 'form']]]),
        new Put(uriTemplate: '/tasks/{id}/failed', controller: TaskFailed::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\') or (is_granted(\'ROLE_COURIER\') and object.isAssignedTo(user))', openapiContext: ['summary' => 'Marks a Task as failed', 'parameters' => [['in' => 'body', 'name' => 'N/A', 'schema' => ['type' => 'object', 'properties' => ['notes' => ['type' => 'string']]], 'style' => 'form']]]),
        new Put(uriTemplate: '/tasks/{id}/assign', controller: TaskAssign::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_COURIER\')', openapiContext: ['summary' => 'Assigns a Task to a messenger', 'parameters' => [['in' => 'body', 'name' => 'N/A', 'schema' => ['type' => 'object', 'properties' => ['username' => ['type' => 'string']]], 'style' => 'form']]]),
        new Delete(uriTemplate: '/tasks/{id}/group', controller: RemoveFromGroup::class, write: false, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'edit\', object)', openapiContext: ['summary' => 'Remove a task from the group to which it belongs']),
        new Put(uriTemplate: '/tasks/{id}/unassign', controller: TaskUnassign::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\') or (is_granted(\'ROLE_COURIER\') and object.isAssignedTo(user))', openapiContext: ['summary' => 'Unassigns a Task from a messenger']),
        new Put(uriTemplate: '/tasks/{id}/cancel', controller: TaskCancel::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\')', openapiContext: ['summary' => 'Cancels a Task']),
        new Post(uriTemplate: '/tasks/{id}/duplicate', controller: TaskDuplicate::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\')', openapiContext: ['summary' => 'Duplicates a Task']),
        new Put(uriTemplate: '/tasks/{id}/reschedule', controller: TaskReschedule::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\')', openapiContext: ['summary' => 'Reschedules a Task', 'parameters' => [['in' => 'body', 'name' => 'N/A', 'schema' => ['type' => 'object', 'properties' => ['after' => ['type' => 'string', 'format' => 'date-time'], 'before' => ['type' => 'string', 'format' => 'date-time']]], 'style' => 'form']]]),
        new Get(uriTemplate: '/tasks/{id}/failure_reasons', controller: TaskFailureReasons::class, security: 'is_granted(\'view\', object)', openapiContext: ['summary' => 'Retrieves possible failure reasons for a Task']),
        new Put(uriTemplate: '/tasks/{id}/incidents', controller: TaskIncident::class, security: 'is_granted(\'view\', object)', openapiContext: ['summary' => 'Creates an incident for a Task']),
        new Get(uriTemplate: '/tasks/{id}/events', controller: TaskEvents::class, security: 'is_granted(\'view\', object)', openapiContext: ['summary' => 'Retrieves events for a Task']),
        new Put(uriTemplate: '/tasks/{id}/restore', controller: TaskRestore::class, denormalizationContext: ['groups' => ['task_operation']], security: 'is_granted(\'ROLE_DISPATCHER\')', openapiContext: ['summary' => 'Restores a Task']),
        new Put(
            uriTemplate: '/tasks/{id}/bio_deliver',
            security: 'is_granted(\'ROLE_OAUTH2_TASKS\')',
            input: BioDeliverInput::class,
            processor: BioDeliverProcessor::class,
            denormalizationContext: ['groups' => ['task_edit']]
        ),
        new Get(uriTemplate: '/tasks/{id}/context', controller: TaskContext::class, security: 'is_granted(\'view\', object)'),
        new Put(uriTemplate: '/tasks/{id}/append_to_comment', controller: TaskAppendToComment::class, security: 'is_granted(\'view\', object)'),
        new GetCollection(
            security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_COURIER\')',
            paginationEnabled: false,
            paginationClientEnabled: true
        ),
        new Post(
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            denormalizationContext: ['groups' => ['task_create']],
            validationContext: ['groups' => ['Default']]
        ),
        new Put(
            uriTemplate: '/tasks/assign',
            controller: TaskBulkAssign::class,
            write: false,
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_COURIER\')',
            openapiContext: [
                'summary' => 'Assigns multiple Tasks at once to a messenger',
                'parameters' => [
                    [
                        'in' => 'body',
                        'name' => 'N/A',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'username' => ['type' => 'string'],
                                'tasks' => ['type' => 'array']
                            ]
                        ],
                        'style' => 'form'
                    ]
                ]
            ]),
        new Put(
            uriTemplate: '/tasks/done',
            controller: TaskBulkMarkAsDone::class,
            write: false,
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_COURIER\')',
            openapiContext: [
                'summary' => 'Mark multiple Tasks as done at once',
                'parameters' => [
                    [
                        'in' => 'body',
                        'name' => 'N/A',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'tasks' => ['type' => 'array']
                            ]
                        ],
                        'style' => 'form'
                    ]
                ]
            ]),
        new Put(
            uriTemplate: '/tasks/images',
            denormalizationContext: ['groups' => ['tasks_images']],
            controller: AddImagesToTasks::class,
            write: false,
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_COURIER\')',
            openapiContext: [
                'summary' => '',
                'parameters' => [
                    [
                        'in' => 'body',
                        'name' => 'N/A',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'tasks' => ['type' => 'array'],
                                'images' => ['type' => 'array']
                            ]
                        ],
                        'style' => 'form'
                    ]
                ]
            ]
        )
    ],
    normalizationContext: ['groups' => ['task', 'delivery', 'address']]
)]
#[ApiFilter(filterClass: TaskOrderFilter::class)]
#[ApiFilter(filterClass: TaskDateFilter::class, properties: ['date'])]
#[ApiFilter(filterClass: TaskFilter::class)]
#[ApiFilter(filterClass: AssignedFilter::class, properties: ['assigned'])]
#[ApiFilter(filterClass: OrganizationFilter::class, properties: ['organization'])]
#[UniqueEntity(fields: ['organization', 'ref'], errorPath: 'ref')]
#[AssertTask]
class Task implements TaggableInterface, OrganizationAwareInterface, PackagesAwareInterface, TimeSlotAwareInterface
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

    #[Groups(['task', 'delivery'])]
    private $id;

    #[Assert\Choice(['PICKUP', 'DROPOFF'])]
    #[Groups(['task', 'task_create', 'task_edit', 'delivery_create', 'delivery'])]
    private $type = self::TYPE_DROPOFF;

    #[Groups(['task', 'delivery'])]
    private $status = self::STATUS_TODO;

    private $delivery;

    #[Assert\NotNull]
    #[Assert\Valid]
    #[Groups(['task', 'task_create', 'task_edit', 'address', 'address_create', 'delivery_create'])]
    private $address;

    private $doneAfter;

    #[Assert\NotBlank]
    #[Assert\Expression('this.getDoneAfter() == null or this.getDoneAfter() < this.getDoneBefore()', message: 'task.before.mustBeGreaterThanAfter')]
    private $doneBefore;

    #[Groups(['task', 'task_create', 'task_edit', 'delivery', 'delivery_create'])]
    private $comments;

    private $events;

    #[Groups(['task', 'delivery'])]
    private $createdAt;

    #[Groups(['task'])]
    private $updatedAt;

    private $previous;

    private $next;

    #[Groups(['task'])]
    private $group;

    #[Groups(['task'])]
    private $assignedTo;

    /**
     * @var \DateTime|null
     */
    private $assignedOn;

    /**
     * @var Collection<int,TaskImage>
     */
    #[Groups(['task_edit'])]
    private $images;

    /**
     * @var int
     */
    private $imageCount = 0;

    #[Groups(['task', 'task_create', 'task_edit'])]
    private $doorstep = false;

    #[Groups(['task', 'task_create'])]
    private $ref;

    /**
     * @var RecurrenceRule|null
     */
    #[Groups(['task'])]
    private $recurrenceRule;

    /**
     * @var array
     */
    #[Groups(['task', 'task_edit', 'delivery'])]
    private $metadata = [];

    /**
     *
     * We actually don't expose the 'weight' property in the API, we expose :
     * - DROPOFF : weight property
     * - PICKUP : sum of weight of all the dropoffs
     * @var int
     */
    #[Groups(['task', 'task_create', 'task_edit', 'delivery', 'delivery_create'])]
    private $weight;

    #[Groups(['task'])]
    private $incidents;

    /**
     * CO2 emissions from the previous task/warehouse to accomplish this task
     */
    #[Groups(['task'])]
    private $emittedCo2 = 0;

    /**
     * Distance from previous task, in meter
     */
    #[Groups(['task'])]
    private $traveledDistanceMeter = 0;

    // Denormalized manually inside the TaskNormalizer
    private ?TimeSlot $timeSlot = null;

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
    /**
     * @return Task
     */
    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }
    /**
     * @return Task
     */
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
    /**
     * @return Task
     */
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
    /**
     * @return bool
     */
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
    /**
     * @return Task
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    #[SerializedName('after')]
    #[Groups(['task', 'task_edit', 'delivery'])]
    public function getAfter()
    {
        return $this->doneAfter;
    }

    /**
     * @return Task
     */
    #[SerializedName('after')]
    #[Groups(['task', 'task_create', 'task_edit', 'delivery', 'delivery_create'])]
    public function setAfter(?\DateTime $doneAfter)
    {
        $this->doneAfter = $doneAfter;

        return $this;
    }

    #[SerializedName('before')]
    #[Groups(['task', 'task_create', 'task_edit', 'delivery', 'delivery_create'])]
    public function getBefore()
    {
        return $this->doneBefore;
    }

    /**
     * @return Task
     */
    #[SerializedName('before')]
    #[Groups(['task', 'task_edit', 'delivery'])]
    public function setBefore(?\DateTime $doneBefore)
    {
        $this->doneBefore = $doneBefore;

        return $this;
    }

    public function getComments()
    {
        return $this->comments;
    }
    /**
     * @return Task
     */
    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
    /**
     * @return ArrayCollection<TaskEvent>
     */
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
    /**
     * @return bool
     */
    public function containsEventWithName($name)
    {
        foreach ($this->events as $e) {
            if ($e->getName() === $name) {
                return true;
            }
        }

        return false;
    }
    /**
     * @return void
     */
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
    /**
     * @return Task
     */
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
    /**
     * @return Task
     */
    public function setNext(Task $next = null)
    {
        $this->next = $next;

        return $this;
    }

    public function hasNext()
    {
        return $this->next !== null;
    }

    #[Groups(['task'])]
    #[SerializedName('isAssigned')]
    public function isAssigned()
    {
        return null !== $this->assignedTo;
    }
    /**
     * @return bool
     */
    public function isAssignedTo(User $courier)
    {
        return $this->isAssigned() && $this->assignedTo === $courier;
    }
    /**
     * @return ?DateTime
     */
    public function getAssignedOn()
    {
        return $this->assignedOn;
    }

    public function getAssignedCourier()
    {
        return $this->assignedTo;
    }

    /**
     * @param \DateTime|null $date
     * @return void
     */
    public function assignTo(User $courier, \DateTime $date = null)
    {
        if (null === $date) {
            @trigger_error('Not specifying a date when calling assignTo() is deprecated', E_USER_DEPRECATED);
        }

        $this->assignedTo = $courier;
        $this->assignedOn = $date;
    }
    /**
     * @return void
     */
    public function unassign()
    {
        $this->assignedTo = null;
        $this->assignedOn = null;
    }
    /**
     * @return bool
     */
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
    /**
     * @return Task
     */
    public function setGroup(TaskGroup $group = null)
    {
        $this->group = $group;

        return $this;
    }
    /**
     * @return Collection<int,TaskImage>
     */
    public function getImages()
    {
        return $this->images;
    }
    /**
     * @return Task
     */
    public function setImages($images)
    {
        foreach ($images as $image) {
            $image->setTask($this);
        }

        $this->images = $images;
        $this->imageCount = count($this->images);

        return $this;
    }
    /**
     * @return Task
     */
    public function addImage($image)
    {
        $this->images->add($image);
        $this->imageCount = count($this->images);

        return $this;
    }
    /**
     * @return Task
     */
    public function addImages($images)
    {
        foreach ($images as $image) {
            $this->addImage($image);
            $image->setTask($this);
        }

        $this->imageCount = count($this->images);

        return $this;
    }
    /**
     * @return Task
     */
    public function duplicate()
    {
        $task = new self();

        $task->setType($this->getType());
        $task->setComments($this->getComments());
        $task->setAddress($this->getAddress());
        $task->setDoneAfter($this->getDoneAfter());
        $task->setDoneBefore($this->getDoneBefore());
        $task->setTags($this->getTags());

        foreach ($this->getPackages() as $package) {
            $task->addPackageWithQuantity($package->getPackage(), $package->getQuantity());
        }

        $task->setWeight($this->getWeight());

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
     */
    public function setDoneBefore(?\DateTime $before)
    {
        return $this->setBefore($before);
    }
    /**
     * @return Task
     */
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
     * @return mixed|string
     */
    #[SerializedName('orgName')]
    #[Groups(['task'])]
    public function getOrganizationName()
    {
        $organization = $this->getOrganization();

        if ($organization) {

            return $organization->getName();
        }

        return '';
    }
    /**
     * @return Task
     */
    public function setRef(string $ref)
    {
        $this->ref = $ref;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public static function toVroomJob(Task $task, $iri = '', $id = null): VroomJob
    {
        $job = new VroomJob();

        $jobId = $task->getId() ?? $id;

        $job->id = $jobId;
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
    /**
     * @return Task
     */
    public function setRecurrenceRule(RecurrenceRule $recurrenceRule)
    {
        $this->recurrenceRule = $recurrenceRule;

        return $this;
    }

    public function getRecurrenceRule(): ?RecurrenceRule
    {
        return $this->recurrenceRule;
    }

    #[SerializedName('images')]
    #[Groups(['task'])]
    public function getImagesWithCache()
    {
        if (0 === $this->imageCount) {
            return new ArrayCollection();
        }

        return $this->getImages();
    }
    /**
     * @return void
     */
    public function setMetadata($key)
    {
        if (func_num_args() === 1 && is_array(func_get_arg(0))) {
            $this->metadata = func_get_arg(0);
        } elseif (func_num_args() === 2) {
            $this->metadata[func_get_arg(0)] = func_get_arg(1);
        }
    }
    /**
     * @return array
     */
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

        $packageTags = $wrappedPackage->getPackage()->getTags();
        $this->addTags($packageTags);

        if (!$this->packages->contains($wrappedPackage)) {
            $this->packages->add($wrappedPackage);
        }
    }
    /**
     * @return void
     */
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
    /**
     * @return void
     */
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

    public function totalPackages(): int
    {
        return array_sum($this->getPackages()->map(function (TaskPackage $package) {
            return $package->getQuantity();
        })->toArray());
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }
    /**
     * @return Task
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }
    /**
     * @return void
     */
    public function addToStore(Store $store)
    {
        $this->setOrganization($store->getOrganization());
    }

    /**
     * @return void
     */
    public function appendToComments($comments)
    {
        $this->comments = implode("\n\n", array_filter([trim($this->getComments()), $comments]));
    }

    #[Groups(['task'])]
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

    public static function fixTimeWindow(Task $task)
    {
        if (null === $task->getAfter()) {
            $after = clone $task->getBefore();
            $after->modify('-15 minutes');
            $task->setAfter($after);
        }
    }

    #[Groups(['barcode'])]
    public function getBarcodes(): array
    {
        $task_code = BarcodeUtils::getRawBarcodeFromTask($this);
        $barcodes = [
            'task' => [$task_code, BarcodeUtils::getToken($task_code)],
            'packages' => [],
        ];

        $packages = $this->getPackages();
        $packageCount = 0;

        $barcodes['packages'] = array_map(
            function($package) use (&$packageCount) {
                $barcodes = BarcodeUtils::getBarcodesFromPackage($package, $packageCount);
                $packageCount += count($barcodes);

                $pkg = $package->getPackage();
                return [
                    'name' => $pkg->getName(),
                    'color' => $pkg->getColor(),
                    'short_code' => $pkg->getShortCode(),
                    'barcodes' => array_map(
                        fn(Barcode $code) => [
                            $code->getRawBarcode(),
                            BarcodeUtils::getToken($code)
                        ],
                        $barcodes
                    )
                ];
            },
            iterator_to_array($packages)
        );

        return $barcodes;
    }

    public function getBarcode(bool $fallback_to_internal = false): ?string
    {
        $barcode = collect($this->getMetadata())->get('barcode');
        if ($this->type === self::TYPE_PICKUP) {
            return null;
        }
        if (is_null($barcode) && $fallback_to_internal) {
            return BarcodeUtils::getRawBarcodeFromTask($this);
        }
        return $barcode;
    }

    public function setBarcode(string $barcode): void
    {
        $this->metadata['barcode'] = $barcode;
    }

    public function getStore(): Store|null
    {
        return $this->getDelivery()?->getStore();
    }

    /**
     * Get cO2 emissions from the previous task/warehouse to accomplish this task
     */
    public function getEmittedCo2()
    {
        return $this->emittedCo2;
    }

    /**
     * Set cO2 emissions from the previous task/warehouse to accomplish this task
     *
     * @return  self
     */
    public function setEmittedCo2($emittedCo2)
    {
        $this->emittedCo2 = $emittedCo2;

        return $this;
    }

    /**
     * Get distance from previous task, in meter
     */
    public function getTraveledDistanceMeter()
    {
        return $this->traveledDistanceMeter;
    }

    /**
     * Set distance from previous task, in meter
     *
     * @return  self
     */
    public function setTraveledDistanceMeter($traveledDistanceMeter)
    {
        $this->traveledDistanceMeter = $traveledDistanceMeter;

        return $this;
    }

    public function isZeroWaste(): bool
    {
        if ($delivery = $this->getDelivery()) {
            if ($order = $delivery->getOrder()) {
                return $order->isZeroWaste();
            }
        }

        return false;
    }

    public function getIUB(): ?int
    {
        $iub_code = collect($this->getMetadata())->get('iub_code');
        if (is_null($iub_code)) {
            return null;
        }
        return intval($iub_code);
    }

    public function setIUB(?int $iub_code): self
    {
        $metadata = $this->getMetadata();
        $metadata['iub_code'] = $iub_code;
        $this->setMetadata($metadata);
        return $this;
    }

    public function getTimeSlot(): ?TimeSlot
    {
        return $this->timeSlot;
    }

    public function setTimeSlot(?TimeSlot $timeSlot): void
    {
        $this->timeSlot = $timeSlot;
    }

}
