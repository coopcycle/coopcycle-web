<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use AppBundle\Action\Delivery\Cancel as CancelDelivery;
use AppBundle\Action\Delivery\Create as CreateDelivery;
use AppBundle\Action\Delivery\Drop as DropDelivery;
use AppBundle\Action\Delivery\Pick as PickDelivery;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Api\Filter\DeliveryOrderFilter;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Edifact\EDIFACTMessageAwareTrait;
use AppBundle\Entity\Package\PackagesAwareInterface;
use AppBundle\Entity\Package\PackagesAwareTrait;
use AppBundle\Entity\Package\PackageWithQuantity;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\ExpressionLanguage\PackagesResolver;
use AppBundle\Pricing\PriceCalculationVisitor;
use AppBundle\Validator\Constraints\CheckDelivery as AssertCheckDelivery;
use AppBundle\Validator\Constraints\Delivery as AssertDelivery;
use AppBundle\Vroom\Shipment as VroomShipment;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/ParcelDelivery",
 *   collectionOperations={
 *     "post"={
 *       "method"="POST",
 *       "denormalization_context"={"groups"={"delivery_create"}},
 *       "controller"=CreateDelivery::class,
 *       "openapi_context"={
 *         "parameters"=Delivery::OPENAPI_CONTEXT_POST_PARAMETERS
 *       },
 *       "security_post_denormalize"="is_granted('create', object)"
 *     },
 *     "check"={
 *       "method"="POST",
 *       "path"="/deliveries/assert",
 *       "write"=false,
 *       "status"=200,
 *       "validation_groups"={"Default", "delivery_check"},
 *       "denormalization_context"={"groups"={"delivery_create"}},
 *       "security_post_denormalize"="is_granted('create', object)",
 *       "openapi_context"={
 *         "summary"="Asserts a Delivery is feasible",
 *         "parameters"=Delivery::OPENAPI_CONTEXT_POST_PARAMETERS
 *       }
 *     },
 *     "from_tasks"={
 *       "method"="POST",
 *       "path"="/deliveries/from_tasks",
 *       "input"=DeliveryInput::class,
 *       "denormalization_context"={"groups"={"delivery_create_from_tasks"}},
 *       "security"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('view', object)"
 *     },
 *     "put"={
 *        "method"="PUT",
 *        "security"="is_granted('edit', object)"
 *     },
 *     "pick"={
 *        "method"="PUT",
 *        "path"="/deliveries/{id}/pick",
 *        "controller"=PickDelivery::class,
 *        "security"="is_granted('edit', object)",
 *        "openapi_context"={
 *          "summary"="Marks a Delivery as picked"
 *        }
 *     },
 *     "drop"={
 *        "method"="PUT",
 *        "path"="/deliveries/{id}/drop",
 *        "controller"=DropDelivery::class,
 *        "security"="is_granted('edit', object)",
 *        "openapi_context"={
 *          "summary"="Marks a Delivery as dropped"
 *        }
 *     },
 *     "cancel"={
 *        "method"="DELETE",
 *        "controller"=CancelDelivery::class,
 *        "write"=false,
 *        "security"="is_granted('edit', object)",
 *        "openapi_context"={
 *          "summary"="Cancels a Delivery"
 *        }
 *     }
 *   },
 *   attributes={
 *     "order"={"createdAt": "DESC"},
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"delivery", "address"}},
 *     "pagination_items_per_page"=15
 *   }
 * )
 * @ApiFilter(OrderFilter::class, properties={"createdAt"})
 * @ApiFilter(DeliveryOrderFilter::class, properties={"dropoff.before"})
 * @AssertDelivery
 * @AssertCheckDelivery(groups={"delivery_check"})
 */
class Delivery extends TaskCollection implements TaskCollectionInterface, PackagesAwareInterface
{
    use PackagesAwareTrait;
    use EDIFACTMessageAwareTrait;

    const VEHICLE_BIKE = 'bike';
    const VEHICLE_CARGO_BIKE = 'cargo_bike';

    /**
     * @Groups({"delivery"})
     */
    protected $id;

    private $order;

    private $vehicle = self::VEHICLE_BIKE;

    /**
     * @Groups({"delivery_create"})
     */
    private $store;

    const OPENAPI_CONTEXT_POST_PARAMETERS = [[
        "name" => "delivery",
        "in"=>"body",
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

        return parent::addTask($task, $position);
    }


    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder(OrderInterface $order)
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
     * @Groups({"delivery"})
     */
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
     * @Groups({"delivery"})
     */
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

    public static function createWithTasks(Task ...$tasks)
    {
        $delivery = self::create();

        $delivery->removeTask($delivery->getPickup());
        $delivery->removeTask($delivery->getDropoff());

        if (count($tasks) > 2) {

            $delivery = $delivery->withTasks(...$tasks);

        } else {

            [ $pickup, $dropoff ] = $tasks;

            $pickup->setType(Task::TYPE_PICKUP);
            $pickup->setDelivery($delivery);

            $dropoff->setType(Task::TYPE_DROPOFF);
            $dropoff->setDelivery($delivery);

            $pickup->setNext($dropoff);
            $dropoff->setPrevious($pickup);

            $delivery->addTask($pickup);
            $delivery->addTask($dropoff);
        }

        return $delivery;
    }

    public function withTasks(Task ...$tasks)
    {
        $this->items->clear();

        $pickup = array_shift($tasks);

        // Make sure the first task is a pickup
        $pickup->setType(Task::TYPE_PICKUP);

        $this->addTask($pickup);

        foreach ($tasks as $dropoff) {
            $dropoff->setPrevious($pickup);
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
     * @param Package $package
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
     * @param \DateTime $after
     * @param \DateTime $before
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
        usort($messages, fn ($a, $b) => $a->getCreatedAt() >= $b->getCreatedAt());
        return $messages;
    }

    public function acceptPriceCalculationVisitor(PriceCalculationVisitor $visitor)
    {
        $visitor->visitDelivery($this);
    }
}
