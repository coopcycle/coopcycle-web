<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\Intangible;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use AppBundle\Validator\Constraints as CustomAssert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\DeliveryRepository")
 * @ORM\EntityListeners({"AppBundle\Entity\Listener\DeliveryListener"})
 * @ApiResource(iri="http://schema.org/ParcelDelivery",
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "accept"={"route_name"="delivery_accept"},
 *     "decline"={"route_name"="delivery_decline"},
 *     "pick"={"route_name"="delivery_pick"},
 *     "deliver"={"route_name"="delivery_deliver"}
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"delivery"}},
 *     "normalization_context"={"groups"={"delivery", "place", "order"}}
 *   }
 * )
 *
 * @CustomAssert\IsValidDeliveryDate(groups="order")
 *
 */
class Delivery extends Intangible
{
    // default status when the delivery is created along the order
    const STATUS_WAITING    = 'WAITING';
    // the delivery has been accepted by a courier
    const STATUS_DISPATCHED = 'DISPATCHED';
    // the delivery has been picked by a courier
    const STATUS_PICKED     = 'PICKED';
    // delivered successfully
    const STATUS_DELIVERED  = 'DELIVERED';
    // the courier had an accident
    const STATUS_ACCIDENT   = 'ACCIDENT';
    // delivery was canceled (by an admin)
    const STATUS_CANCELED   = 'CANCELED';

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="Address", cascade={"persist"})
     * @ApiProperty(iri="https://schema.org/Place")
     */
    private $originAddress;

    /**
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="Address", cascade={"persist"})
     * @ApiProperty(iri="https://schema.org/Place")
     */
    private $deliveryAddress;

    /**
     * @ORM\OneToOne(targetEntity="Order", inversedBy="delivery")
     * @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     */
    private $order;

    /**
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $courier;

    /**
     * @var string
     *
     * @Groups({"order"})
     * @ORM\Column(type="string", nullable=true)
     */
    private $status;

    /**
     * @Groups({"order"})
     * @ORM\Column(type="datetime")
     * @Assert\NotBlank(groups={"order"})
     */
    private $date;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank(groups={"order"})
     */
    private $distance;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank(groups={"order"})
     */
    private $duration;

    /**
     * @ORM\OneToMany(targetEntity="DeliveryEvent", mappedBy="delivery")
     * @ORM\OrderBy({"createdAt" = "ASC"})
     */
    private $events;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $data = [];

    public function __construct(Order $order = null)
    {
        $this->status = self::STATUS_WAITING;

        if ($order) {
            $order->setDelivery($this);
            $this->order = $order;
        }

        $this->events = new ArrayCollection();
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getOriginAddress()
    {
        return $this->originAddress;
    }

    public function setOriginAddress(Address $originAddress)
    {
        $this->originAddress = $originAddress;

        return $this;
    }

    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(Address $deliveryAddress)
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    public function getDistance()
    {
        return $this->distance;
    }

    public function setDistance($distance)
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;

        return $this;
    }

    public function isCalculated()
    {
        return null !== $this->duration && null !== $this->distance;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Sets courier.
     *
     * @param ApiUser $courier
     *
     * @return $this
     */
    public function setCourier(ApiUser $courier)
    {
        $this->courier = $courier;

        return $this;
    }

    /**
     * Gets courier.
     *
     * @return ApiUser
     */
    public function getCourier()
    {
        return $this->courier;
    }

    /**
     * @return ArrayCollection|OrderEvent[]
    */
    public function getEvents()
    {
        return $this->events;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function getActualDuration()
    {
        if ($this->status === self::STATUS_DELIVERED) {

            $criteria = Criteria::create()
                ->andWhere(Criteria::expr()->eq('eventName', self::STATUS_DISPATCHED));
            $dispatched = $this->events->matching($criteria)->first();

            $criteria = Criteria::create()
                ->andWhere(Criteria::expr()->eq('eventName', self::STATUS_DELIVERED));
            $delivered = $this->events->matching($criteria)->first();

            if ($dispatched && $delivered) {
                $diff = $delivered->getCreatedAt()->diff($dispatched->getCreatedAt());

                $hours = $diff->format('%h');
                $minutes = $diff->format('%i');

                return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
            }
        }
    }

    /**
     * Custom order validation.
     * @Assert\Callback(groups={"order"})
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        $order = $this->getOrder();

        // Validate distance
        $maxDistance = $order->getRestaurant()->getMaxDistance();

        $constraint = new Assert\LessThan(['value' => $maxDistance]);
        $context
            ->getValidator()
            ->inContext($context)
            ->atPath('distance')
            ->validate($this->distance, $constraint, [Constraint::DEFAULT_GROUP]);

        // Validate opening hours
        if (!$order->getRestaurant()->isOpen($this->getDate())) {
             $context
                ->buildViolation(sprintf('Restaurant is closed at %s', $this->getDate()->format('Y-m-d H:i:s')))
                ->atPath('date')
                ->addViolation();
        }
    }

}
