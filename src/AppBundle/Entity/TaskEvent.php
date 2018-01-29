<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

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
     */
    private $task;

    /**
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct(Task $task, $name)
    {
        $this->name = $name;
        $this->task = $task;

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

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
