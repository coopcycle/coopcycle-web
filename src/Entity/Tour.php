<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Dto\TourInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Action\Task\DeleteTour as DeleteTourController;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use AppBundle\Api\Filter\DateFilter;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "post"={
 *       "method"="POST",
 *       "input"=TourInput::class,
 *       "security"="is_granted('ROLE_DISPATCHER')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_DISPATCHER')"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "input"=TourInput::class,
 *       "security"="is_granted('ROLE_DISPATCHER')"
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "security"="is_granted('ROLE_DISPATCHER')",
 *       "controller"=DeleteTourController::class
 *     }
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"tour"}},
 *     "normalization_context"={"groups"={"task_collection", "task", "tour"}}
 *   }
 * )
 * @ApiFilter(DateFilter::class, properties={"date"})
 */
class Tour extends TaskCollection implements TaskCollectionInterface
{
    private $date;
    
    protected $id;

    /**
     * @var string
     * @Groups({"tour", "task"})
     */
    protected $name;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function addTask(Task $task, $position = null)
    {
        $task->setTour($this);

        return parent::addTask($task, $position);
    }

    public function removeTask(Task $task)
    {
        $task->setTour(null);
        parent::removeTask($task);
    }

    public function getTaskPosition(Task $task)
    {
        foreach ($this->getItems() as $item) {
            if ($item->getTask() === $task) {
                return $item->getPosition();
            }
        }

        return 0;
    }

    public function getDate()
    {
        return $this->date;
    }

    /**
     * @SerializedName("date")
     * @Groups({"task_collection", "task_collections"})
     */
    public function getDateString()
    {
        return $this->date->format('Y-m-d');
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;

        return $this;
    }    
}
