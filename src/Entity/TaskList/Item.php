<?php

namespace AppBundle\Entity\TaskList;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\TaskList;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An Item of a tasklist.
 */
class Item
{
    private $id;

    private $parent;

    /**
     * @Assert\Valid()
     * @Groups({"task_list"})
     */
    private $task;

    /**
     * @Assert\Valid()
     * @Groups({"task_list"})
     */
    private $tour;

    /**
     * @Groups({"task_list"})
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

    public function setParent(TaskList $parent = null)
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

    public function getTour()
    {
        return $this->tour;
    }

    public function setTour($tour)
    {
        $this->tour = $tour;

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

    public function getItemIri(IriConverterInterface $iriConverter)
    {
        if ($this->task) {
            return $iriConverter->getIriFromItem($this->task);
        } else if ($this->tour) {
            return $iriConverter->getIriFromItem($this->tour);
        }
    }
}
