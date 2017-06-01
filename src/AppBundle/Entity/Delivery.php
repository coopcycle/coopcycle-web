<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
 *     "denormalization_context"={"groups"={"order"}},
 *     "normalization_context"={"groups"={"order", "place"}}
 *   }
 * )
 */
class Delivery extends Intangible
{
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
     * @Assert\NotBlank()
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="integer")
     */
    private $distance;

    /**
     * @ORM\Column(type="integer")
     */
    private $duration;

    public function __construct(Order $order = null)
    {
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
}
