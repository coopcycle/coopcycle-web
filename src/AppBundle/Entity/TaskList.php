<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\Task\CollectionTrait as TaskCollectionTrait;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="task_list", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="task_list_unique", columns={"date", "courier_id"})}
 * )
 * @ApiResource(
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"},
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"task_collection", "task"}}
 *   }
 * )
 */
class TaskList extends TaskCollection implements TaskCollectionInterface
{
    use TaskCollectionTrait;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     * @ORM\JoinColumn(nullable=false)
     */
    private $courier;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     * @Groups({"task_collection"})
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     * @Groups({"task_collection"})
     */
    private $updatedAt;

    public function getId()
    {
        return $this->id;
    }

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

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
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
    public function removeTask(Task $task)
    {
        $task->unassign();

        return parent::removeTask($task);
    }
}
