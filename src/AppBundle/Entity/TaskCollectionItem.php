<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A TaskCollectionItem is a task belonging to a TaskCollection.
 *
 * @ORM\Entity
 * @ORM\Table(name="task_collection_item", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="task_collection_item_unique", columns={"parent_id", "task_id"})}
 * )
 */
class TaskCollectionItem
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="TaskCollection", inversedBy="items")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $parent;

    /**
     * @ORM\ManyToOne(targetEntity="Task", cascade={"persist"})
     * @ORM\JoinColumn(name="task_id", referencedColumnName="id")
     * @Groups({"task_collection"})
     */
    private $task;

    /**
     * @ORM\Column(name="position", type="integer")
     * @Groups({"task_collection"})
     */
    private $position;

    public function getId()
    {
        return $this->id;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent(TaskCollection $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function setTask($task)
    {
        $this->task = $task;

        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }
}
