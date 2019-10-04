<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Delivery\Package as DeliveryPackage;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Validator\Constraints\Delivery as AssertDelivery;
use AppBundle\Validator\Constraints\CheckDelivery as AssertCheckDelivery;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/ParcelDelivery",
 *   collectionOperations={
 *     "post"={"method"="POST"},
 *     "check"={
 *         "method"="POST",
 *         "path"="/deliveries/check",
 *         "write"=false,
 *         "status"=200,
 *         "validation_groups"={"Default", "delivery_check"}
 *     }
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"}
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"delivery", "place", "order"}}
 *   }
 * )
 *
 * @AssertDelivery
 * @AssertCheckDelivery(groups={"delivery_check"})
 */
class Delivery extends TaskCollection implements TaskCollectionInterface
{
    const VEHICLE_BIKE = 'bike';
    const VEHICLE_CARGO_BIKE = 'cargo_bike';

    const COLORS_LIST = ['#213ab2', '#b2213a', '#5221b2', '#93c63f', '#b22182', '#3ab221', '#b25221', '#2182b2', '#3ab221', '#9c21b2', '#c63f4f', '#b2217f', '#82b221', '#5421b2', '#3f93c6', '#21b252', '#c6733f'];

    /**
     * @Groups({"delivery"})
     */
    protected $id;

    private $order;

    private $weight;

    private $vehicle = self::VEHICLE_BIKE;

    private $store;

    private $packages;

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
        $pickup = $this->getPickup();
        $dropoff = $this->getDropoff();

        if (null === $pickup && $task->isPickup()) {
            parent::addTask($task, $position);
            return;
        }

        if (null === $dropoff && $task->isDropoff()) {
            parent::addTask($task, $position);
            return;
        }

        throw new \RuntimeException('No additional task can be added');
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
        return $this->weight;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;

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

    public function getPickup()
    {
        foreach ($this->getTasks() as $task) {
            if ($task->getType() === Task::TYPE_PICKUP) {
                return $task;
            }
        }
    }

    public function getDropoff()
    {
        foreach ($this->getTasks() as $task) {
            if ($task->getType() === Task::TYPE_DROPOFF) {
                return $task;
            }
        }
    }

    public static function create()
    {
        return new self();
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

    public function getColor()
    {
        if(!is_null($this->getId())) {
            return $this::COLORS_LIST[$this->getId() % count($this::COLORS_LIST)];
        }

    }

    public function setStore(Store $store)
    {
        $this->store = $store;
    }

    public function getStore()
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

    public function setPackages($packages)
    {
        $this->packages = $packages;
    }

    public function getPackages()
    {
        return $this->packages;
    }

    public function hasPackages()
    {
        return count($this->packages) > 0;
    }

    public function addPackageWithQuantity(Package $package, $quantity = 1)
    {
        if (0 === $quantity) {
            return;
        }

        $deliveryPackage = new DeliveryPackage($this);
        $deliveryPackage->setPackage($package);
        $deliveryPackage->setQuantity($quantity);

        $this->packages->add($deliveryPackage);
    }

    public function hasPackage(Package $package)
    {
        foreach ($this->packages as $p) {
            if ($p->getPackage() === $package) {
                return true;
            }
        }

        return false;
    }

    public function getQuantityForPackage(Package $package)
    {
        foreach ($this->packages as $p) {
            if ($p->getPackage() === $package) {
                return $p->getQuantity();
            }
        }

        return 0;
    }

    private static function createTaskObject(?Task $task)
    {
        $taskObject = new \stdClass();
        if ($task) {
            $taskObject->address = $task->getAddress();
            $taskObject->createdAt = $task->getCreatedAt();
            $taskObject->before = $task->getDoneBefore();
        }

        return $taskObject;
    }

    public static function toExpressionLanguageValues(Delivery $delivery)
    {
        $pickup = self::createTaskObject($delivery->getPickup());
        $dropoff = self::createTaskObject($delivery->getDropoff());

        return [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
            'vehicle' => $delivery->getVehicle(),
            'pickup' => $pickup,
            'dropoff' => $dropoff,
        ];
    }
}
