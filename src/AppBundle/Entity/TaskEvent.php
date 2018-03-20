<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskEvent
{
    private $id;

    private $task;

    /**
     * @Groups({"task"})
     */
    private $name;

    /**
     * @Groups({"task"})
     */
    private $notes;

    /**
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
