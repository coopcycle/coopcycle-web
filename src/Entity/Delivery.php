<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Delivery\Cancel as CancelDelivery;
use AppBundle\Action\Delivery\Drop as DropDelivery;
use AppBundle\Action\Delivery\Pick as PickDelivery;
use AppBundle\Action\Delivery\BulkAsync as BulkAsyncDelivery;
use AppBundle\Action\Delivery\PODExport as PODExportDelivery;
use AppBundle\Action\Delivery\SuggestOptimizations as SuggestOptimizationsController;
use AppBundle\Api\Dto\DeliveryFromTasksInput;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Api\Dto\OptimizationSuggestions;
use AppBundle\Api\Filter\DeliveryOrderFilter;
use AppBundle\Api\Filter\DeliveryTaskDateFilter;
use AppBundle\Api\State\DeliveryCreateOrUpdateProcessor;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Edifact\EDIFACTMessageAwareTrait;
use AppBundle\Entity\Package\PackagesAwareInterface;
use AppBundle\Entity\Package\PackagesAwareTrait;
use AppBundle\Entity\Package\PackageWithQuantity;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Validator\Constraints\CheckDelivery as AssertCheckDelivery;
use AppBundle\Validator\Constraints\Delivery as AssertDelivery;
use AppBundle\Vroom\Shipment as VroomShipment;
use DeliveryPODExportInput;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 */
#[ApiResource(
    types: ['http://schema.org/ParcelDelivery'],
    operations: [
        new Get(security: 'is_granted(\'view\', object)'),
        new Put(
            denormalizationContext: ['groups' => ['delivery_create']],
            security: 'is_granted(\'edit\', object)',
            input: DeliveryInputDto::class,
            output: Delivery::class,
            processor: DeliveryCreateOrUpdateProcessor::class
        ),
        new Put(
            uriTemplate: '/deliveries/{id}/pick',
            controller: PickDelivery::class,
            openapiContext: ['summary' => 'Marks a Delivery as picked'],
            security: 'is_granted(\'edit\', object)'
        ),
        new Put(
            uriTemplate: '/deliveries/{id}/drop',
            controller: DropDelivery::class,
            openapiContext: ['summary' => 'Marks a Delivery as dropped'],
            security: 'is_granted(\'edit\', object)'
        ),
        new Delete(
            controller: CancelDelivery::class,
            openapiContext: ['summary' => 'Cancels a Delivery'],
            security: 'is_granted(\'edit\', object)',
            write: false,
            name: 'cancel'
        ),
        new Post(
            inputFormats: ['jsonld' => ['application/ld+json']],
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'delivery',
                        'in' => 'body',
                        'schema' => [
                            'type' => 'object',
                            'required' => ['dropoff'],
                            'properties' => [
                                'dropoff' => ['$ref' => '#/definitions/Task-task_create'],
                                'pickup' => ['$ref' => '#/definitions/Task-task_create']
                            ]
                        ],
                        'style' => 'form'
                    ]
                ]
            ],
            denormalizationContext: ['groups' => ['delivery_create']],
            securityPostDenormalize: 'is_granted(\'create\', object)',
            input: DeliveryInputDto::class,
            output: Delivery::class,
            processor: DeliveryCreateOrUpdateProcessor::class
        ),
        new Post(
            uriTemplate: '/deliveries/assert',
            status: 200,
            openapiContext: [
                'summary' => 'Asserts a Delivery is feasible',
                'parameters' => [
                    [
                        'name' => 'delivery',
                        'in' => 'body',
                        'schema' => [
                            'type' => 'object',
                            'required' => ['dropoff'],
                            'properties' => [
                                'dropoff' => ['$ref' => '#/definitions/Task-task_create'],
                                'pickup' => ['$ref' => '#/definitions/Task-task_create']
                            ]
                        ],
                        'style' => 'form'
                    ]
                ]
            ],
            denormalizationContext: ['groups' => ['delivery_create']],
            securityPostDenormalize: 'is_granted(\'create\', object)',
            validationContext: ['groups' => ['Default', 'delivery_check']],
            write: false
        ),
        new Post(
            uriTemplate: '/deliveries/from_tasks',
            denormalizationContext: ['groups' => ['delivery_create_from_tasks']],
            security: 'is_granted(\'ROLE_ADMIN\')',
            input: DeliveryFromTasksInput::class,
            output: Delivery::class,
            processor: DeliveryCreateOrUpdateProcessor::class
        ),
        new Post(
            uriTemplate: '/deliveries/suggest_optimizations',
            types: ['OptimizationSuggestions'],
            status: 200,
            controller: SuggestOptimizationsController::class,
            openapiContext: [
                'summary' => 'Suggests optimizations for a delivery',
                'parameters' => [
                    [
                        'name' => 'delivery',
                        'in' => 'body',
                        'schema' => [
                            'type' => 'object',
                            'required' => ['dropoff'],
                            'properties' => [
                                'dropoff' => ['$ref' => '#/definitions/Task-task_create'],
                                'pickup' => ['$ref' => '#/definitions/Task-task_create']
                            ]
                        ],
                        'style' => 'form'
                    ]
                ]
            ],
            normalizationContext: [
                'groups' => ['optimization_suggestions'],
                'api_sub_level' => true
            ],
            denormalizationContext: ['groups' => ['delivery_create']],
            securityPostDenormalize: 'is_granted(\'create\', object)',
            output: OptimizationSuggestions::class,
            write: false,
        ),
        new Post(
            uriTemplate: '/deliveries/import_async',
            inputFormats: ['csv' => ['text/csv']],
            controller: BulkAsyncDelivery::class,
            security: 'is_granted(\'ROLE_OAUTH2_DELIVERIES\')',
            deserialize: false
        ),
        new Post(
            uriTemplate: '/deliveries/pod_export',
            controller: PODExportDelivery::class,
            /* input: DeliveryPODExportInput::class, */
            write: false,
            deserialize: false
        )
    ],
    normalizationContext: ['groups' => ['delivery', 'address', 'package_delivery_order_minimal']],
    denormalizationContext: ['groups' => ['order_create']],
    order: ['createdAt' => 'DESC'],
    paginationItemsPerPage: 15
)]
#[AssertDelivery]
#[AssertCheckDelivery(groups: ['delivery_check'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['createdAt'])]
#[ApiFilter(filterClass: DeliveryOrderFilter::class, properties: ['dropoff.before'])]
#[ApiFilter(filterClass: DeliveryTaskDateFilter::class)]
#[ApiResource(
    uriTemplate: '/stores/{id}/deliveries',
    types: ['http://schema.org/ParcelDelivery'],
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(fromClass: Store::class, toProperty: 'store')
    ],
    normalizationContext: ['groups' => ['delivery', 'address', 'package_delivery_order_minimal']],
    security: "is_granted('edit', request)"
)]
class Delivery extends TaskCollection implements TaskCollectionInterface, PackagesAwareInterface
{
    use PackagesAwareTrait;
    use EDIFACTMessageAwareTrait;

    const VEHICLE_BIKE = 'bike';
    const VEHICLE_CARGO_BIKE = 'cargo_bike';

    #[Groups(['delivery'])]
    protected $id;

    #[Groups(['package_delivery_order_minimal'])]
    private $order;

    private $vehicle = self::VEHICLE_BIKE;

    public const TYPE_SIMPLE = 'simple';
    public const TYPE_MULTI_DROPOFF = 'multi_dropoff';
    public const TYPE_MULTI_PICKUP = 'multi_pickup';
    public const TYPE_MULTI_MULTI = 'multi_multi';

    #[Groups(['delivery_create'])]
    private $store;

    public function __construct()
    {
        parent::__construct();

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setDelivery($this);

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setDelivery($this);

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $this->addTask($pickup);
        $this->addTask($dropoff);

        $this->packages = new ArrayCollection();
    }

    public function addTask(Task $task, $position = null)
    {
        $task->setDelivery($this);
        $taskCollection = parent::addTask($task, $position);

        $deliveryPosition = $taskCollection->findTaskPosition($task);
        $task->setMetadata('delivery_position', $deliveryPosition + 1); // we prefer it to be 1-indexed for user display

        return $taskCollection;
    }


    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order)
    {
        $this->order = $order;

        return $this;
    }

    public function getWeight()
    {
        $totalWeight = null;
        foreach ($this->getTasks() as $task) {
            if (null !== $task->getWeight()) {
                $totalWeight += $task->getWeight();
            }
        }
        return $totalWeight;
    }

    /**
     * @deprecated Set weight via Task::setWeight()
     * @param $weight
     * @return self
     */
    public function setWeight($weight)
    {
        if (null !== $weight) {
            foreach ($this->getTasks() as $task) {
                if ($task->isDropoff()) {
                    $task->setWeight($weight);
                    break;
                }
            }
        }

        return $this;
    }

    public function getVehicle()
    {
        return $this->vehicle;
    }

    public function setVehicle($vehicle)
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    /**
     * @return Task|null
     */
    #[Groups(['delivery'])]
    public function getPickup()
    {
        foreach ($this->getTasks() as $task) {
            if ($task->getType() === Task::TYPE_PICKUP) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @return Task|null
     */
    #[Groups(['delivery'])]
    public function getDropoff()
    {
        if (count($this->getTasks()) > 2) {
            foreach (array_reverse($this->getTasks()) as $task) {
                if ($task->getType() === Task::TYPE_DROPOFF) {
                    return $task;
                }
            }
        } else {
            foreach ($this->getTasks() as $task) {
                if ($task->getType() === Task::TYPE_DROPOFF) {
                    return $task;
                }
            }
        }

        return null;
    }

    public static function create()
    {
        return new self();
    }

    public static function canCreateWithTasks(Task ...$tasks): bool
    {
        if (count($tasks) < 2) {
            return false;
        }

        // the first task must be a pickup
        if (!$tasks[0]->isPickup()) {
            return false;
        }

        // the last task must be a dropoff
        if (!$tasks[count($tasks) - 1]->isDropoff()) {
            return false;
        }

        return true;
    }

    public static function createWithTasks(Task ...$tasks)
    {
        $delivery = self::create();
        $delivery->withTasks(...$tasks);
        return $delivery;
    }

    public function withTasks(Task ...$tasks)
    {
        $this->removeTask($this->getPickup());
        $this->removeTask($this->getDropoff());

        // reset array keys/indices
        $this->items->clear();

        if (count($tasks) > 2) {

            $pickups  = array_values(array_filter($tasks, fn($t) => $t->isPickup()));
            $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));

            $type = self::getType($tasks);

            switch ($type) {
                case self::TYPE_MULTI_DROPOFF:
                case self::TYPE_SIMPLE:
                    foreach ($tasks as $task) {
                        if ($task->isDropoff()) {
                            $task->setPrevious($pickups[0]);
                        }
                        $this->addTask($task);
                    }
                    break;
                default:
                    // For multiple pickups we don't set constraints
                    foreach ($tasks as $task) {
                        $this->addTask($task);
                    }
                    break;
            }

        } else {

            [$pickup, $dropoff] = $tasks;

            $pickup->setType(Task::TYPE_PICKUP);
            $pickup->setNext($dropoff);

            $dropoff->setType(Task::TYPE_DROPOFF);
            $dropoff->setPrevious($pickup);

            $this->addTask($pickup);
            $this->addTask($dropoff);
        }

        return $this;
    }

    /**
     * @deprecated Set address via Task::setAddress()
     * @param $pickupAddress
     * @param $dropoffAddress
     * @return self
     */
    public static function createWithAddress($pickupAddress, $dropoffAddress)
    {
        $delivery = self::createWithDefaults();

        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);

        return $delivery;
    }

    public static function createWithDefaults()
    {
        $pickupDoneBefore = new \DateTime();
        $pickupDoneBefore->modify('+1 day');

        $dropoffDoneBefore = clone $pickupDoneBefore;
        $dropoffDoneBefore->modify('+1 hour');

        $delivery = self::create();

        $delivery->getPickup()->setDoneBefore($pickupDoneBefore);
        $delivery->getDropoff()->setDoneBefore($dropoffDoneBefore);

        return $delivery;
    }

    public function setStore(Store $store)
    {
        $this->store = $store;

        foreach ($this->getTasks() as $task) {
            $task->setOrganization($store->getOrganization());
        }
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function isAssigned()
    {
        return $this->getPickup()->isAssigned() && $this->getDropoff()->isAssigned();
    }

    /**
     * Assigns the courier to all tasks in the delivery
     */
    public function assignTo(User $user): void
    {
        $tasks = $this->getTasks();
        array_walk(
            $tasks,
            function (Task $task) use ($user) {
                $task->assignTo($user);
            }
        );
    }

    /**
     * Unassigns the courier from all tasks in the delivery
     */
    public function unassign(): void
    {
        $tasks = $this->getTasks();
        array_walk(
            $tasks,
            function (Task $task) {
                $task->unassign();
            }
        );
    }

    public function isCompleted()
    {
        foreach ($this->getTasks() as $task) {
            if (!$task->isCompleted()) {

                return false;
            }
        }

        return true;
    }

    /**
     * @return ArrayCollection<PackageWithQuantity>
     */
    public function getPackages()
    {
        $packages = new ArrayCollection();

        $hash = new \SplObjectStorage();

        foreach ($this->getTasks() as $task) {
            if ($task->hasPackages()) {
                foreach ($task->getPackages() as $package) {
                    $object = $package->getPackage();
                    if (isset($hash[$object])) {
                        $hash[$object] += $package->getQuantity();
                    } else {
                        $hash[$object] = $package->getQuantity();
                    }
                }
            }
        }

        foreach ($hash as $package) {
            $quantity = $hash[$package];
            $packages->add(new PackageWithQuantity($package, $quantity));
        }

        return $packages;
    }

    /**
     * @deprecated set quantity via Task::setPackageWithQuantity()
     * @param $quantity
     * @return void
     */
    public function addPackageWithQuantity(Package $package, $quantity = 1)
    {
        if (0 === $quantity) {
            return;
        }

        foreach ($this->getTasks() as $task) {
            if ($task->isDropoff()) {
                $task->addPackageWithQuantity($package, $quantity);
                break;
            }
        }
    }

    public function setPickupRange(\DateTime $after, \DateTime $before)
    {
        $this->getPickup()
            ->setDoneAfter($after)
            ->setDoneBefore($before);

        return $this;
    }

    /**
     * @deprecated Set dropoff range via Task::setDoneAfter() and Task::setDoneBefore()
     * @return $this
     */
    public function setDropoffRange(\DateTime $after, \DateTime $before)
    {
        $this->getDropoff()
            ->setDoneAfter($after)
            ->setDoneBefore($before);

        return $this;
    }

    public function getOwner()
    {
        $store = $this->getStore();
        if (null !== $store) {
            return $store;
        }

        $order = $this->getOrder();
        if (null !== $order) {
            return $order->getRestaurant();
        }
    }

    public static function toVroomShipment(Delivery $delivery, $dropoff, $pickupIri, $dropoffIri): VroomShipment
    {
        $shipment = new VroomShipment();

        $shipment->pickup = Task::toVroomJob($delivery->getPickup(), $pickupIri);
        $shipment->delivery = Task::toVroomJob($dropoff, $dropoffIri);

        return $shipment;
    }

    public function getImages()
    {
        $images = new ArrayCollection();

        foreach ($this->getTasks() as $task) {
            foreach ($task->getImages() as $image) {
                $images->add($image);
            }
        }

        return $images;
    }

    public function hasImages()
    {
        return count($this->getImages()) > 0;
    }

    public function getEdifactMessagesTimeline(): array
    {
        $messages = array_merge(...array_map(function (Task $task) {
            return $task->getEdifactMessages()->toArray();
        }, $this->getTasks()));
        usort($messages, fn($a, $b) => $a->getCreatedAt() >= $b->getCreatedAt());
        return $messages;
    }

    public static function getType(array $tasks): string
    {
        $pickups = array_filter($tasks, fn($t) => $t->isPickup());
        $dropoffs = array_filter($tasks, fn($t) => $t->isDropoff());

        $isSimple = count($pickups) === 1 && count($dropoffs) === 1;

        if ($isSimple) {
            return self::TYPE_SIMPLE;
        }

        $isMultiDropoffs = count($pickups) === 1 && count($dropoffs) > 1;
        $isMultiPickups = count($pickups) > 1 && count($dropoffs) === 1;

        $pickupsWithPackages = array_filter($pickups, fn($t) => count($t->getPackages()) > 0);
        $dropoffsWithPackages = array_filter($dropoffs, fn($t) => count($t->getPackages()) > 0);

        $isCleanMultiPickups = $isMultiPickups && count($dropoffsWithPackages) === 0;
        $isCleanMultiDropoffs = $isMultiDropoffs && count($pickupsWithPackages) === 0;

        if ($isCleanMultiPickups) {
            return self::TYPE_MULTI_PICKUP;
        }

        if ($isCleanMultiDropoffs) {
            return self::TYPE_MULTI_DROPOFF;
        }

        return self::TYPE_MULTI_MULTI;
    }
}
