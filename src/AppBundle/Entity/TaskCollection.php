<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({"delivery" = "AppBundle\Entity\Delivery"})
 */
abstract class TaskCollection
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="TaskCollectionItem", mappedBy="parent", cascade={"all"})
     */
    protected $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function addTask(Task $task)
    {
        $item = new TaskCollectionItem();
        $item->setTask($task);
        $item->setParent($this);
        $item->setPosition(-1);

        $this->items->add($item);

        return $this;
    }

    public function getTasks()
    {
        return $this->items->map(function (TaskCollectionItem $item) {
            return $item->getTask();
        });
    }
}
