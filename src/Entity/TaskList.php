<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\TaskList\Create as CreateTaskListController;
use AppBundle\Action\TaskList\Optimize as OptimizeController;
use AppBundle\Action\TaskList\SetItems as SetTaskListItemsController;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Api\Dto\AddTaskListItemsInput;
use AppBundle\Api\Dto\RemoveTaskListItemsInput;
use AppBundle\Api\Dto\TaskListOrderInput;
use AppBundle\Api\Dto\MyTaskListDto;
use AppBundle\Api\Filter\DateFilter;
use AppBundle\Api\State\AddTaskListItemsProcessor;
use AppBundle\Api\State\RemoveTaskListItemProcessor;
use AppBundle\Api\State\RemoveTaskListItemsProcessor;
use AppBundle\Api\State\SetTaskListOrderProcessor;
use AppBundle\Api\State\TaskListProvider;
use AppBundle\Api\State\MyTasksProvider;
use AppBundle\Entity\Task\CollectionTrait as TaskCollectionTrait;
use AppBundle\Entity\Task\VersionedCollectionInterface;
use AppBundle\Entity\Task\VersionedCollectionTrait;
use AppBundle\Entity\TaskList\Item;
use Doctrine\Common\Collections\ArrayCollection;
use Shahonseven\ColorHash;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * A TaskList represents the daily planning for a courier.
 * It is a concrete implementation of a TaskCollection.
 */
#[ApiResource(
    operations: [
        new Get(
            requirements: ['id' => '[0-9]+'],
            // Make sure to add requirements for operations like "/task_lists/v2" to work
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Patch(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Put(
            uriTemplate: '/task_lists/set_items/{date}/{username}',
            controller: SetTaskListItemsController::class,
            openapiContext: [
                'summary' => 'Deprecated. Replaces the whole task list; use the items and order endpoints instead.',
                'description' => 'This takes the list as absolute state, so a task left out of the payload is unassigned. A client that has merely not heard of a task sends exactly the same bytes as one deliberately removing it, which is why work has been silently unassigned in the field for years. Use POST /api/task_lists/{id}/items, DELETE /api/task_lists/{id}/items/{type}/{itemId} and PUT /api/task_lists/{id}/order, which name what they change.',
            ],
            deprecationReason: 'Replaced by the task list items and order endpoints, which name what they change instead of replacing the whole list. See https://github.com/coopcycle/coopcycle-web/issues/5372',
            sunset: self::SET_ITEMS_SUNSET,
            extraProperties: [
                'deprecated_since' => '2026-10-17',
                'successor_version' => [
                    '/api/task_lists/{id}/items',
                    '/api/task_lists/{id}/order',
                ],
            ],
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            read: false
        ),
        new Get(
            uriTemplate: '/me/tasks/{date}',
            uriVariables: ['date'],
            provider: MyTasksProvider::class,
            openapiContext: [
                'summary' => 'Retrieves the collection of Task resources assigned to the authenticated token.',
                'parameters' => [
                    [
                        'in' => 'path',
                        'name' => 'date',
                        'required' => true,
                        'type' => 'string',
                        'format' => 'date'
                    ],
                    [
                        'in' => 'query',
                        'name' => 'tours',
                        'required' => false,
                        'type' => 'boolean',
                        'description' => 'When true, tours are returned as nested "Tour" items wrapping their tasks. When absent (the default), tours are flattened into a list of tasks, for backwards compatibility with older versions of the app.'
                    ]
                ]
            ],
            normalizationContext: ['groups' => ['task_list', 'task', 'delivery', 'address']],
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_COURIER\')',
            output: MyTaskListDto::class,
            write: false
        ),
        new Get(
            uriTemplate: '/task_lists/{id}/optimize',
            controller: OptimizeController::class,
            security: 'is_granted(\'ROLE_ADMIN\')',
            serialize: false
        ),
        new GetCollection(
            openapiContext: ['summary' => 'Legacy endpoint, please use \'/api/task_lists/v2\' instead. Retrieves Tasklists as lists of tasks, not tasks and tours, with expanded tasks. Used by store integrations that wants to track tasks statuses.'],
            normalizationContext: [
                'groups' => [
                    'task_list',
                    'task_collection',
                    'task',
                    'delivery',
                    'address'
                ]
            ],
            security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_OAUTH2_TASKS\')',
            provider: TaskListProvider::class
        ),
        new Post(
            controller: CreateTaskListController::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Post(
            uriTemplate: '/task_lists/{id}/items',
            requirements: ['id' => '[0-9]+'],
            openapiContext: [
                'summary' => 'Puts tasks and tours on a task list, leaving the rest of the list alone.',
                'description' => 'Preferred over PUT /api/task_lists/set_items/{date}/{username}, which takes the whole list as absolute state and therefore lets a client that is out of sync silently unassign work it never knew about. Items already on another list are moved, not duplicated.',
            ],
            input: AddTaskListItemsInput::class,
            denormalizationContext: ['groups' => ['task_list_items']],
            processor: AddTaskListItemsProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            read: false
        ),
        new Post(
            uriTemplate: '/task_lists/{id}/items/remove',
            requirements: ['id' => '[0-9]+'],
            openapiContext: [
                'summary' => 'Takes tasks and tours off a task list, and unassigns them.',
                'description' => 'The counterpart of POST /api/task_lists/{id}/items. Items not on the list are ignored, so asking twice is not an error. Prefer this over repeating the single-item DELETE: removing an item renumbers the ones that remain, so several concurrent single removals contend for the same rows.',
            ],
            input: RemoveTaskListItemsInput::class,
            denormalizationContext: ['groups' => ['task_list_items']],
            processor: RemoveTaskListItemsProcessor::class,
            // Nothing is created here; POST only because a DELETE carrying a
            // list of items in its body is not reliably served.
            status: 200,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            read: false
        ),
        new Delete(
            uriTemplate: '/task_lists/{id}/items/{type}/{itemId}',
            requirements: ['id' => '[0-9]+', 'type' => 'tasks|tours', 'itemId' => '[0-9]+'],
            openapiContext: [
                'summary' => 'Takes one task or tour off a task list, and unassigns it.',
                'description' => 'Preferred over leaving the item out of PUT /api/task_lists/set_items/{date}/{username}, which cannot tell a deliberate removal from a client that never knew about the item. Scoped to this list: if the item is no longer on it, nothing happens and the response is still 204.',
            ],
            // Only the task list is identified by the URI; `type` and
            // `itemId` name the item to take off it.
            uriVariables: ['id'],
            processor: RemoveTaskListItemProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Put(
            uriTemplate: '/task_lists/{id}/order',
            requirements: ['id' => '[0-9]+'],
            openapiContext: [
                'summary' => 'Reorders a task list, without changing which items are on it.',
                'description' => 'Takes every item currently on the list, in the wanted order. Refused with 422 if the payload would add, drop or repeat anything — use the items endpoints for that. If-Match is required and carries the task list version; a mismatch answers 409.',
            ],
            input: TaskListOrderInput::class,
            denormalizationContext: ['groups' => ['task_list_order']],
            processor: SetTaskListOrderProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            read: false
        ),
        new GetCollection(
            uriTemplate: '/task_lists/v2',
            openapiContext: ['summary' => 'Retrieves TaskLists as lists of tours and tasks.'],
            security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_OAUTH2_TASKS\')',
            provider: TaskListProvider::class
        )
    ],
    normalizationContext: ['groups' => ['task_list']]
)]
#[ApiFilter(filterClass: DateFilter::class, properties: ['date'])]
class TaskList implements TaskCollectionInterface, VersionedCollectionInterface
{
    /**
     * When `set_items` stops working.
     *
     * Far enough out that every mobile app in the field has had time to be
     * replaced — they cannot be upgraded on our schedule, and they are the
     * reason this is a deprecation rather than a removal. Bring it forward
     * once the logs show nothing is calling it.
     *
     * @see \AppBundle\Action\TaskList\SetItems
     */
    public const SET_ITEMS_SUNSET = '2027-10-01';

    use TaskCollectionTrait;
    use VersionedCollectionTrait;

    #[Groups(['task_list'])]
    private $id;

    #[Assert\Valid]
    #[Groups(['task_list'])]
    protected $items;

    /**
     * Legacy props to filter then display a TaskList as a list of tasks
     * Can be get and set, but not persisted to the database
     * @deprecated
     */
    protected $tempLegacyTaskStorage;

    private $date;

    private $courier;

    /**
     * @var Vehicle
     */
    #[Groups(['task_list'])]
    private $vehicle;

    /**
     * @var Trailer
     */
    #[Groups(['task_list'])]
    private $trailer;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDate()
    {
        return $this->date;
    }

    #[SerializedName('date')]
    #[Groups(['task_list'])]
    public function getDateString()
    {
        return $this->date->format('Y-m-d');
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;

        return $this;
    }

    public function getCourier()
    {
        return $this->courier;
    }

    public function setCourier($courier)
    {
        $this->courier = $courier;

        return $this;
    }

    /**
     * The ordered tasks of the TaskList. Is useful for calculating distances or operations on tasks, but for dispatch read/write you would prefer `getItems` as it will return the tasklist as a list of both tasks and tours.
     *
     * @return Task[]
     */
    public function getTasks() {
        $items = $this->getItems();
        $tasks = [];

        foreach($items as $item) {
            if (is_null($item->getTask())) {
                $tasks = array_merge($tasks, $item->getTour()->getTasks());
            } else {
                array_push($tasks, $item->getTask());
            }
        }
        return $tasks;
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

    /**
     * Only used in `EntityChangeSetProcessor` class to sync $task state into $tasklist.
     * Should not generally not be used; please use TaskListManager->assign
     */
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

    #[SerializedName('username')]
    #[Groups(['task_list'])]
    public function getUsername()
    {
        return $this->getCourier()->getUsername();
    }

    #[SerializedName('color')]
    #[Groups(['task_list'])]
    public function getColor()
    {
        $colorHash = new ColorHash();
        $username = $this->getCourier()->getUsername();
        $hexColor = $colorHash->hex($username);

        return $hexColor;
    }

    /**
     * Get the value of items
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the value of items
     *
     * @return  self
     */
    public function setItems($items)
    {
        foreach($items as $item) {
            $this->items->add($item);
            $item->setParent($this);
        }

        return $this;
    }

    public function addItem(Item $item) {
        $this->items->add($item);
        $item->setParent($this);
    }

    public function appendTask(Task $task) {
        $item = new Item();
        $item->setTask($task);
        $item->setPosition($this->getItems()->count());
        $this->addItem($item);

        $task->assignTo($this->getCourier());

        return $this;
    }

     /**
     * Clear the assigned items
     */
    public function clear()
    {
        foreach($this->items as $item) {
            $item->setParent(null);
        }
        return $this->items->clear();
    }

    /**
     * Get legacy props to manage a TaskList as a list of tasks
     */
    public function getTempLegacyTaskStorage()
    {
        return $this->tempLegacyTaskStorage;
    }

    /**
     * Set legacy props to manage a TaskList as a list of tasks
     *
     * @return  self
     */
    public function setTempLegacyTaskStorage($tempLegacyTaskStorage)
    {
        $this->tempLegacyTaskStorage = $tempLegacyTaskStorage;

        return $this;
    }

    /**
     * Get the value of vehicle
     *
     * @return Vehicle|void
     */
    public function getVehicle()
    {
        return $this->vehicle;
    }

    /**
     * Set the value of vehicle
     *
     * @param  Vehicle  $vehicle
     *
     * @return  self
     */
    public function setVehicle(?Vehicle $vehicle)
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    /**
     * Get the value of trailer
     *
     * @return  Trailer
     */
    public function getTrailer()
    {
        return $this->trailer;
    }

    /**
     * Set the value of trailer
     *
     * @param  Trailer  $trailer
     *
     * @return  self
     */
    public function setTrailer(?Trailer $trailer)
    {
        $this->trailer = $trailer;

        return $this;
    }
}
