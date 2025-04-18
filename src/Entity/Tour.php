<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Api\Dto\TourInput;
use AppBundle\Api\State\TourProcessor;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use AppBundle\Api\Filter\DateFilter;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Vroom\Job as VroomJob;
use AppBundle\Vroom\Shipment as VroomShipment;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Put(
            input: TourInput::class,
            processor: TourProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new GetCollection(security: 'is_granted(\'ROLE_DISPATCHER\')', paginationEnabled: false),
        new Post(
            input: TourInput::class,
            processor: TourProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        )
    ],
    denormalizationContext: ['groups' => ['tour']],
    normalizationContext: ['groups' => ['task_collection', 'tour']]
)]
#[ApiFilter(filterClass: DateFilter::class, properties: ['date'])]
class Tour extends TaskCollection implements TaskCollectionInterface
{
    private $date;

    protected $id;

    /**
     * @var Item
     */
    private $taskListItem;

    /**
     * @var string
     */
    #[Groups(['tour', 'task'])]
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

    #[SerializedName('date')]
    #[Groups(['task_collection', 'task_collections'])]
    public function getDateString()
    {
        return $this->date->format('Y-m-d');
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;

        return $this;
    }

    public static function toVroomStep(Tour $tour, $tourIri) : VroomJob
    {

        $tasks = $tour->getTasks();
        return Task::toVroomJob($tasks[0], $tourIri);

    }

    /**
     * Get the value of taskListItem
     *
     * @return Item
     */
    public function getTaskListItem()
    {
        return $this->taskListItem;
    }

    /**
     * Set the value of taskListItem
     *
     *
     * @return  self
     */
    public function setTaskListItem(Item $taskListItem)
    {
        $this->taskListItem = $taskListItem;

        return $this;
    }
}
