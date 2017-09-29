<?php

namespace AppBundle\Entity;


use AppBundle\Entity\Menu\Modifier;
use AppBundle\Utils\CartItem;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * An order is a confirmation of a transaction (a receipt), which can contain multiple line items, each represented by an Offer that has been accepted by the customer.
 *
 * @see http://schema.org/Order Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\OrderRepository")
 * @ORM\EntityListeners({"AppBundle\Entity\Listener\OrderListener"})
 * @ORM\Table(name="order_")
 * @ApiResource(iri="http://schema.org/Order",
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "post"={"method"="POST"},
 *     "my_orders"={"method"="GET", "route_name"="my_orders"}
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "pay"={"route_name"="order_pay"},
 *     "accept"={"route_name"="order_accept"},
 *     "refuse"={"route_name"="order_refuse"},
 *     "ready"={"route_name"="order_ready"}
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order"}},
 *     "normalization_context"={"groups"={"order", "place"}}
 *   }
 * )
 */
class Order
{
    const STATUS_CREATED    = 'CREATED';
    const STATUS_WAITING    = 'WAITING'; // FIXME Should be STATUS_PAID
    const STATUS_ACCEPTED   = 'ACCEPTED';
    const STATUS_REFUSED    = 'REFUSED';
    const STATUS_READY      = 'READY';
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
     * @var ApiUser Party placing the order or paying the invoice.
     *
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $customer;

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
     * @ORM\OneToMany(targetEntity="OrderEvent", mappedBy="order")
     * @ORM\OrderBy({"createdAt" = "ASC"})
     */
    private $events;

    /**
     * @var string
     *
     * @Groups({"order"})
     * @ORM\Column(type="string", nullable=true)
     */
    private $status;

    /**
     * @Assert\NotNull
     * @Assert\Valid
     * @Groups({"order"})
     * @ORM\OneToOne(targetEntity="Delivery", mappedBy="order", cascade={"all"})
     */
    private $delivery;

    /**
     * @Gedmo\Timestampable(on="create")
     * @Groups({"order"})
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    public function __construct()
    {
        $this->status = self::STATUS_CREATED;
        $this->orderedItem = new ArrayCollection();
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
     * Sets orderedItem.
     *
     * @param OrderItem[] $orderedItem
     *
     * @return $this
     */
    public function setOrderedItem($orderedItem)
    {
        $orderedItem = array_map(function (OrderItem $orderedItem) {
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

    public function addCartItem(CartItem $cartItem, MenuItem $menuItem) {

        $orderedItem = new OrderItem();
        $orderedItem->setMenuItem($menuItem);
        $orderedItem->setQuantity($cartItem->getQuantity());
        $orderedItem->setPrice($cartItem->getUnitPrice());

        foreach ($cartItem->getModifierChoices() as $modifierId => $selectedMenuItems) {

            $modifier = $menuItem->getModifiers()->filter(function ($element) use ($modifierId) {
                return $element->getId() == $modifierId;
            })->first();

            foreach ($selectedMenuItems as $selectedModifierId) {
                $orderedItemModifier = new OrderItemModifier();

                // get the price for each selected menu item (depends on the Modifier's calculus strategy)
                $menuItem = $modifier->getModifierChoices()->filter(
                    function (Modifier $element) use ($selectedModifierId) {
                        return $element->getId() == $selectedModifierId;
                    }
                )->first();

                $orderedItemModifier->setName($menuItem->getName());
                $orderedItemModifier->setDescription($menuItem->getDescription());
                $orderedItemModifier->setAdditionalPrice($menuItem->getPrice());
                $orderedItemModifier->setModifier($modifier);
                $orderedItem->addModifier($orderedItemModifier);
            }
        }

        $this->addOrderedItem($orderedItem);

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
            $total += $orderedItem->getPrice() * $orderedItem->getQuantity();
        }

        return $total;
    }

    public function getDeliveryTime()
    {
        // if ($this->status === self::STATUS_DELIVERED) {

        //     $criteria = Criteria::create()
        //         ->andWhere(Criteria::expr()->eq('eventName', self::STATUS_ACCEPTED));
        //     $accepted = $this->events->matching($criteria)->first();

        //     $criteria = Criteria::create()
        //         ->andWhere(Criteria::expr()->eq('eventName', self::STATUS_DELIVERED));
        //     $delivered = $this->events->matching($criteria)->first();

        //     if ($accepted && $delivered) {
        //         $diff = $delivered->getCreatedAt()->diff($accepted->getCreatedAt());

        //         $hours = $diff->format('%h');
        //         $minutes = $diff->format('%i');

        //         return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
        //     }
        // }
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

    /**
     * @return ArrayCollection|OrderEvent[]
    */
    public function getEvents()
    {
        return $this->events;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function setDelivery(Delivery $delivery)
    {
        $this->delivery = $delivery;
        $delivery->setOrder($this);

        return $this;
    }
}
