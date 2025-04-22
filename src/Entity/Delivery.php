<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Delivery\Cancel as CancelDelivery;
use AppBundle\Action\Delivery\Create as CreateDelivery;
use AppBundle\Action\Delivery\Drop as DropDelivery;
use AppBundle\Action\Delivery\Pick as PickDelivery;
use AppBundle\Action\Delivery\Edit as EditDelivery;
use AppBundle\Action\Delivery\BulkAsync as BulkAsyncDelivery;
use AppBundle\Action\Delivery\SuggestOptimizations as SuggestOptimizationsController;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Api\Dto\OptimizationSuggestions;
use AppBundle\Api\Filter\DeliveryOrderFilter;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Edifact\EDIFACTMessageAwareTrait;
use AppBundle\Entity\Package\PackagesAwareInterface;
use AppBundle\Entity\Package\PackagesAwareTrait;
use AppBundle\Entity\Package\PackageWithQuantity;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\ExpressionLanguage\PackagesResolver;
use AppBundle\Validator\Constraints\CheckDelivery as AssertCheckDelivery;
use AppBundle\Validator\Constraints\Delivery as AssertDelivery;
use AppBundle\Vroom\Shipment as VroomShipment;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'view\', object)'),
        new Put(
            controller: EditDelivery::class,
            security: 'is_granted(\'edit\', object)',
            denormalizationContext: ['groups' => ['delivery_create']]
        ),
        new Put(
            uriTemplate: '/deliveries/{id}/pick',
            controller: PickDelivery::class,
            security: 'is_granted(\'edit\', object)',
            openapiContext: ['summary' => 'Marks a Delivery as picked']
        ),
        new Put(
            uriTemplate: '/deliveries/{id}/drop',
            controller: DropDelivery::class, security: 'is_granted(\'edit\', object)', openapiContext: ['summary' => 'Marks a Delivery as dropped']),
        new Delete(controller: CancelDelivery::class, write: false, security: 'is_granted(\'edit\', object)', openapiContext: ['summary' => 'Cancels a Delivery'], name: 'cancel'),
        new Post(
            denormalizationContext: ['groups' => ['delivery_create']],
            controller: CreateDelivery::class,
            openapiContext: ['parameters' => [['name' => 'delivery', 'in' => 'body', 'schema' => ['type' => 'object', 'required' => ['dropoff'], 'properties' => ['dropoff' => ['$ref' => '#/definitions/Task-task_create'], 'pickup' => ['$ref' => '#/definitions/Task-task_create']]], 'style' => 'form']]],
            inputFormats: ['jsonld' => ['application/ld+json']],
            securityPostDenormalize: 'is_granted(\'create\', object)'
        ),
        new Post(
            uriTemplate: '/deliveries/assert',
            write: false,
            status: 200,
            validationContext: ['groups' => ['Default', 'delivery_check']],
            denormalizationContext: ['groups' => ['delivery_create']],
            securityPostDenormalize: 'is_granted(\'create\', object)',
            openapiContext: ['summary' => 'Asserts a Delivery is feasible', 'parameters' => [['name' => 'delivery', 'in' => 'body', 'schema' => ['type' => 'object', 'required' => ['dropoff'], 'properties' => ['dropoff' => ['$ref' => '#/definitions/Task-task_create'], 'pickup' => ['$ref' => '#/definitions/Task-task_create']]], 'style' => 'form']]]
        ),
        new Post(
            uriTemplate: '/deliveries/from_tasks',
            input: DeliveryInput::class,
            denormalizationContext: ['groups' => ['delivery_create_from_tasks']],
            security: 'is_granted(\'ROLE_ADMIN\')'
        ),
        new Post(
            uriTemplate: '/deliveries/suggest_optimizations',
            write: false,
            status: 200,
            controller: SuggestOptimizationsController::class,
            output: OptimizationSuggestions::class,
            denormalizationContext: ['groups' => ['delivery_create']],
            normalizationContext: ['groups' => ['optimization_suggestions'], 'api_sub_level' => true],
            securityPostDenormalize: 'is_granted(\'create\', object)',
            openapiContext: ['summary' => 'Suggests optimizations for a delivery', 'parameters' => [['name' => 'delivery', 'in' => 'body', 'schema' => ['type' => 'object', 'required' => ['dropoff'], 'properties' => ['dropoff' => ['$ref' => '#/definitions/Task-task_create'], 'pickup' => ['$ref' => '#/definitions/Task-task_create']]], 'style' => 'form']]]
        ),
        new Post(
            uriTemplate: '/deliveries/import_async', deserialize: false, inputFormats: ['csv' => ['text/csv']], controller: BulkAsyncDelivery::class, security: 'is_granted(\'ROLE_OAUTH2_DELIVERIES\')')], types: ['http://schema.org/ParcelDelivery'], order: ['createdAt' => 'DESC'], denormalizationContext: ['groups' => ['order_create']], normalizationContext: ['groups' => ['delivery', 'address']], paginationItemsPerPage: 15)]
#[AssertDelivery]
#[AssertCheckDelivery(groups: ['delivery_check'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['createdAt'])]
#[ApiFilter(filterClass: DeliveryOrderFilter::class, properties: ['dropoff.before'])]
#[ApiResource(uriTemplate: '/stores/{id}/deliveries.{_format}', uriVariables: ['id' => new Link(fromClass: \AppBundle\Entity\Store::class, identifiers: ['id'])], status: 200, types: ['http://schema.org/ParcelDelivery'], filters: ['annotated_app_bundle_entity_delivery_api_platform_core_bridge_doctrine_orm_filter_order_filter', 'annotated_app_bundle_entity_delivery_app_bundle_api_filter_delivery_order_filter'], normalizationContext: ['groups' => ['delivery', 'address']], operations: [new GetCollection()])]
class Delivery extends TaskCollection implements TaskCollectionInterface, PackagesAwareInterface
{
    use PackagesAwareTrait;
    use EDIFACTMessageAwareTrait;

    const VEHICLE_BIKE = 'bike';
    const VEHICLE_CARGO_BIKE = 'cargo_bike';

    #[Groups(['delivery'])]
    protected $id;

    private $order;

    private $vehicle = self::VEHICLE_BIKE;

    #[Groups(['delivery_create', 'pricing_deliveries'])]
    private $store;

    /**
     * @var ?ArbitraryPrice
     */
    #[Groups(['delivery_create'])]
    private $arbitraryPrice;

    const OPENAPI_CONTEXT_POST_PARAMETERS = [[
        "name" => "delivery",
        "in" => "body",
        "schema" => [
            "type" => "object",
            "required" => ["dropoff"],
            "properties" => [
                "dropoff" => ['$ref' => '#/definitions/Task-task_create'],
                "pickup" => ['$ref' => '#/definitions/Task-task_create'],
            ]
        ],
        "style" => "form"
    ]];

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


    public function getOrder()
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

            $pickup = array_shift($tasks);

            // Make sure the first task is a pickup
            $pickup->setType(Task::TYPE_PICKUP);

            $this->addTask($pickup);

            foreach ($tasks as $dropoff) {
                $dropoff->setPrevious($pickup);
                $this->addTask($dropoff);
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

    private static function createTaskObject(?Task $task)
    {
        $taskObject = new \stdClass();
        if ($task) {

            return $task->toExpressionLanguageObject();
        }

        return $taskObject;
    }

    private static function createOrderObject(?Order $order)
    {
        $object = new \stdClass();
        if ($order) {
            $object->itemsTotal = $order->getItemsTotal();
        } else {
            $object->itemsTotal = 0;
        }

        return $object;
    }

    public static function toExpressionLanguageValues(Delivery $delivery)
    {
        $pickup = self::createTaskObject($delivery->getPickup());
        $dropoff = self::createTaskObject($delivery->getDropoff());
        $order = self::createOrderObject($delivery->getOrder());

        $emptyTaskObject = new \stdClass();
        $emptyTaskObject->type = '';

        return [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
            'vehicle' => $delivery->getVehicle(),
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'packages' => new PackagesResolver($delivery),
            'order' => $order,
            'task' => $emptyTaskObject,
        ];
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

    /**
     * Get the value of arbitraryPrice
     */
    public function hasArbitraryPrice(): bool
    {
        return !is_null($this->arbitraryPrice);
    }

    /**
     * Get the value of arbitraryPrice
     */
    public function getArbitraryPrice(): ?ArbitraryPrice
    {
        return $this->arbitraryPrice;
    }

    /**
     * Set the value of arbitraryPrice
     */
    public function setArbitraryPrice(ArbitraryPrice $arbitraryPrice): self
    {
        $this->arbitraryPrice = $arbitraryPrice;

        return $this;
    }
}
