<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class Group implements TaggableInterface
{
    use TaggableTrait;

    /**
     * @Groups({"task"})
     */
    protected $id;

    /**
     * @Groups({"task"})
     * @Assert\Type(type="string")
     */
    protected $name;

    protected $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getTasks()
    {
        return $this->tasks;
    }

    public function removeTask(Task $task)
    {
        $task->setGroup(null);

        $this->tasks->removeElement($task);
    }
}
