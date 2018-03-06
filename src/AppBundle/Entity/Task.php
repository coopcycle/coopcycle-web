<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\Group as TaskGroup;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Entity\TaskRepository")
 * @ORM\EntityListeners({"AppBundle\Entity\Listener\TaskListener"})
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
class Task
{
    const TYPE_DROPOFF = 'DROPOFF';
    const TYPE_PICKUP = 'PICKUP';

    const STATUS_TODO = 'TODO';
    const STATUS_FAILED = 'FAILED';
    const STATUS_DONE = 'DONE';

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"task"})
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     * @Groups({"task"})
     */
    private $type = self::TYPE_DROPOFF;

    /**
     * @ORM\Column(type="string")
     * @Groups({"task"})
     */
    private $status = self::STATUS_TODO;

    /**
     * @ORM\ManyToOne(targetEntity="Delivery")
     * @ORM\JoinColumn(nullable=true)
     */
    private $delivery;

    /**
     * @ORM\ManyToOne(targetEntity="Address", cascade={"persist"})
     * @Groups({"task"})
     */
    private $address;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $doneAfter;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $doneBefore;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"task"})
     */
    private $comments;

    /**
     * @ORM\OneToMany(targetEntity="TaskEvent", mappedBy="task", cascade={"all"})
     * @ORM\OrderBy({"createdAt" = "ASC"})
     * @Groups({"task"})
     */
    private $events;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $updatedAt;

    /**
     * @ORM\OneToOne(targetEntity="Task")
     * @ORM\JoinColumn(name="previous_task_id", referencedColumnName="id", nullable=true)
     */
    private $previous;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Task\Group", cascade={"persist"})
     * @ORM\JoinColumn(name="group_id", referencedColumnName="id", nullable=true)
     */
    private $group;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     * @ORM\JoinColumn(name="assigned_to", referencedColumnName="id", nullable=true)
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

    public function setDoneAfter($doneAfter)
    {
        $this->doneAfter = $doneAfter;

        return $this;
    }

    public function getDoneBefore()
    {
        return $this->doneBefore;
    }

    public function setDoneBefore($doneBefore)
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
