<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\CollectionTrait as TaskCollectionTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A TaskCollection is the database representation of a Task\CollectionInterface.
 * It uses Doctrine's Inheritance Mapping to implement a OneToMany relationship with TaskCollectionItem.
 * There are two concrete implementations of TaskCollection: Delivery & TaskList.
 *
 * @see http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/inheritance-mapping.html
 */
abstract class TaskCollection
{
    use TaskCollectionTrait;

    protected $id;

    /**
     * @Assert\Valid()
     * @Groups({"task_collection", "task"})
     */
    protected $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Mandatory for serialization to work.
     */
    public function getItems()
    {
        $iterator = $this->items->getIterator();

        $iterator->uasort(function ($a, $b) {
            if ($a->getPosition() === $b->getPosition()) {
                return 0;
            }

            return $a->getPosition() < $b->getPosition() ? -1 : 1;
        });

        return new ArrayCollection(iterator_to_array($iterator));
    }

    public function getMaxPosition() {
        $maxPosition = -1;
        foreach ($this->getItems() as $i) {
            $maxPosition = $i->getPosition() > $maxPosition ? $i->getPosition() : $maxPosition;
        }

        return $maxPosition;
    }

    public function addTask(Task $task, $position = null)
    {
        // create the collection item if necessary
        $item = null;
        $created = false;
        $items = $this->getItems();

        $item = $items->filter(function ($item) use ($task) {
            return $item->getTask() === $task;
        })->first();

        if (!$item) {
            $item = new TaskCollectionItem();
            $item->setTask($task);
            $item->setParent($this);
            $this->items->add($item);
            $created = true;
        }

        // 2 cases
        // case 1 : insertion - increment the position of the items after position
        // case 2 : move
        if ($created) {
            $position = is_null($position) ? $this->getMaxPosition() + 1 : $position;
            foreach ($items as $i) {
                if ($i->getPosition() >= $position && $item !== $i) {
                    $i->setPosition($i->getPosition() + 1);
                }
            }
        } else {
            $position = is_null($position) ? $this->getMaxPosition() : $position;
            // moving up : decrement positions between the old one (inf) and the new one (sup)
            if ($item->getPosition() > $position) {
                foreach ($items as $i) {
                    if ($i->getPosition() <= $position && $i->getPosition() >= $item->getPosition() && $item !== $i) {
                        $i->setPosition($i->getPosition() - 1);
                    }
                }
            }
            // moving down : increment the position between the old one (sup) and the new one (inf)
            else if ($item->getPosition() < $position) {
                foreach ($items as $i) {
                    if ($i->getPosition() >= $position && $i->getPosition() <= $item->getPosition() && $item !== $i) {
                        $i->setPosition($i->getPosition() - 1);
                    }
                }
            }
        }

        $item->setPosition($position);

        return $this;
    }

    public function removeTask(Task $task)
    {
        foreach ($this->items as $item) {
            if ($item->getTask() === $task) {
                $this->items->removeElement($item);
                $item->setParent(null);
                break;
            }
        }
    }

    public function getTasks()
    {
        return $this->getItems()->map(function (TaskCollectionItem $item) {
            return $item->getTask();
        })->toArray();
    }

    public function containsTask(Task $task)
    {
        foreach ($this->getTasks() as $t) {
            if ($task === $t) {
                return true;
            }
        }

        return false;
    }

    public function setTasks(array $tasks)
    {
        if (count(array_filter(array_keys($tasks), 'is_string')) > 0) {
            throw new \InvalidArgumentException('$tasks must be an array indexed by integers');
        }

        $newTasks = new \SplObjectStorage();
        foreach ($tasks as $position => $task) {
            $newTasks[$task] = $position;
        }

        $currentTasks = new \SplObjectStorage();
        foreach ($this->getItems() as $item) {
            $currentTasks[$item->getTask()] = $item->getPosition();
        }

        $tasksToRemove = [];
        foreach ($currentTasks as $task) {
            if (!$newTasks->contains($task)) {
                $tasksToRemove[] = $task;
            }
        }

        foreach ($newTasks as $task) {
            $this->addTask($task, $newTasks[$task]);
        }

        foreach ($tasksToRemove as $task) {
            $this->removeTask($task);
        }

        return $this;
    }

    /**
     * Find item at position or return null
     */
    public function findAt($position) {
        foreach ($this->getItems() as $item) {
            if ($item->getPosition() === $position) {
                return $item;
            }
        }
    }
}
