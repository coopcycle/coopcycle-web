<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * An order is a confirmation of a transaction (a receipt), which can contain multiple line items, each represented by an Offer that has been accepted by the customer.
 *
 * @see http://schema.org/Order Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\OrderRepository")
 * @ORM\Table(name="order_")
 * @ApiResource(iri="http://schema.org/Order",
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "post"={"method"="POST"}
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "pay"={"route_name"="order_pay"},
 *     "accept"={"route_name"="order_accept"},
 *     "pick"={"route_name"="order_pick"},
 *     "deliver"={"route_name"="order_deliver"}
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order"}},
 *     "normalization_context"={"groups"={"order", "place"}}
 *   }
 * )
 */
class Order
{
    const STATUS_CREATED = 'CREATED';
    const STATUS_WAITING = 'WAITING';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_READY = 'READY';
    const STATUS_PICKED = 'PICKED';
    const STATUS_ACCIDENT = 'ACCIDENT';
    const STATUS_DELIVERED = 'DELIVERED';

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     */
    private $id;

    /**
     * @var Person Party placing the order or paying the invoice.
     *
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $customer;

    /**
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $courier;

    /**
     * @var Restaurant
     *
     * @Groups({"order", "place"})
     * @ORM\ManyToOne(targetEntity="Restaurant")
     * @ApiProperty(iri="https://schema.org/restaurant")
     */
    private $restaurant;

    /**
     * @var OrderItem The item ordered.
     *
     * @Groups({"order"})
     * @ORM\OneToMany(targetEntity="OrderItem", mappedBy="order", cascade={"all"})
     */
    private $orderedItem;

    /**
     * @var DeliveryAddress
     *
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="DeliveryAddress")
     * @ApiProperty(iri="https://schema.org/place")
     */
    private $deliveryAddress;

    /**
     * @var string
     *
     * @Groups({"order"})
     * @ORM\Column(type="string", nullable=true)
     */
    private $status;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    public function __construct() {
        $this->status = self::STATUS_CREATED;
        $this->orderedItem = new ArrayCollection();
    }

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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

    /**
     * Sets customer.
     *
     * @param ApiUser $customer
     *
     * @return $this
     */
    public function setCustomer(ApiUser $customer)
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Gets customer.
     *
     * @return ApiUser
     */
    public function getCustomer()
    {
        return $this->customer;
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
     * Sets orderedItem.
     *
     * @param OrderItem $orderedItem
     *
     * @return $this
     */
    public function setOrderedItem($orderedItem)
    {
        $orderedItem = array_map(function($orderedItem) {
            return $orderedItem->setOrder($this);
        }, $orderedItem);

        $this->orderedItem = $orderedItem;

        return $this;
    }

    /**
     * Gets orderedItem.
     *
     * @return OrderItem
     */
    public function getOrderedItem()
    {
        return $this->orderedItem;
    }

    public function addOrderedItem(OrderItem $orderedItem)
    {
        $orderedItem->setOrder($this);
        $this->orderedItem->add($orderedItem);

        return $this;
    }

    /**
     * Sets restaurant.
     *
     * @param Restaurant $restaurant
     *
     * @return $this
     */
    public function setRestaurant(Restaurant $restaurant)
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    /**
     * Gets restaurant.
     *
     * @return Restaurant
     */
    public function getRestaurant()
    {
        return $this->restaurant;
    }

    /**
     * Sets deliveryAddress.
     *
     * @param DeliveryAddress $deliveryAddress
     *
     * @return $this
     */
    public function setDeliveryAddress(DeliveryAddress $deliveryAddress)
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    /**
     * Gets deliveryAddress.
     *
     * @return DeliveryAddress
     */
    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getTotal()
    {
        $total = 0;
        foreach ($this->orderedItem as $orderedItem) {
            $total += $orderedItem->getProduct()->getPrice() * $orderedItem->getQuantity();
        }

        return $total;
    }

    /**
     * Gets the value of status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets the value of status.
     *
     * @param string $status the status
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }
}
