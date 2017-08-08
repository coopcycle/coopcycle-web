<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Model\NameTrait;
use AppBundle\Entity\Model\PriceTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An order item is a line of an order. It includes the quantity and shipping details of a bought offer.
 *
 * @see http://schema.org/OrderItem Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\EntityListeners({"AppBundle\Entity\Listener\OrderItemListener"})
 * @ApiResource(iri="http://schema.org/OrderItem",
 *   attributes={
 *     "denormalization_context"={"groups"={"order", "order_item"}},
 *     "normalization_context"={"groups"={"order"}}
 *   }
 * )
 */
class OrderItem
{
    use NameTrait;
    use PriceTrait;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var MenuItem
     *
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Menu\Item")
     * @ApiProperty(iri="https://schema.org/MenuItem")
     * @Groups({"order"})
     */
    private $menuItem;

    /**
     * @var int
     *
     * @Assert\NotBlank()
     * @ORM\Column(type="integer")
     * @Groups({"order"})
     */
    private $quantity;

    /**
     * @var Order
     *
     * @ORM\ManyToOne(targetEntity="Order", inversedBy="orderedItem")
     * @ORM\JoinColumn(nullable=false)
     */
    private $order;

    // FIXME Can't use constructor or denormalization won't work
    // public function __construct(MenuItem $menuItem = null)
    // {
    //     $this->menuItem = $menuItem;
    //     if ($menuItem) {
    //         $this->name = $menuItem->getName();
    //         $this->price = $menuItem->getPrice();
    //     }
    // }

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
     * Sets quantity.
     *
     * @param int $quantity
     *
     * @return $this
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Gets quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Sets menuItem.
     *
     * @param MenuItem $menuItem
     *
     * @return $this
     */
    public function getMenuItem()
    {
        return $this->menuItem;
    }

    /**
     * Gets menuItem.
     *
     * @return MenuItem
     */
    public function setMenuItem(MenuItem $menuItem)
    {
        $this->menuItem = $menuItem;

        $this->setName($menuItem->getName());
        $this->setPrice($menuItem->getPrice());

        return $this;
    }

    /**
     * Sets order.
     *
     * @param Order $order
     *
     * @return $this
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Gets order.
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }
}
