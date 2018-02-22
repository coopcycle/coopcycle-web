<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class TaskEvent
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Task", inversedBy="events")
     * @ORM\JoinColumn(nullable=false)
     */
    private $task;

    /**
     * @ORM\Column(type="string")
     * @Groups({"task"})
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"task"})
     */
    private $notes;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $createdAt;

    public function __construct(Task $task, $name, $notes = null)
    {
        $this->name = $name;
        $this->task = $task;
        $this->notes = $notes;

        $task->getEvents()->add($this);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
