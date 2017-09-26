<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\Intangible;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 *
 * @ORM\Entity
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
 */
class Delivery extends Intangible
{
    const STATUS_WAITING    = 'WAITING';
    const STATUS_DISPATCHED = 'DISPATCHED';
    const STATUS_PICKED     = 'PICKED';
    const STATUS_DELIVERED  = 'DELIVERED';
    const STATUS_ACCIDENT   = 'ACCIDENT';
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
     * @ORM\OneToOne(targetEntity="DeliveryAddress", inversedBy="delivery")
     * @ApiProperty(iri="https://schema.org/Place")
     */
    private $deliveryAddress;

    /**
     * @ORM\OneToOne(targetEntity="Order", inversedBy="delivery")
     * @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     */
    private $order;

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

    public function __construct(Order $order = null)
    {
        $this->status = self::STATUS_WAITING;

        if ($order) {
            $order->setDelivery($this);
            $this->order = $order;
        }
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

    public function setDeliveryAddress(DeliveryAddress $deliveryAddress)
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
