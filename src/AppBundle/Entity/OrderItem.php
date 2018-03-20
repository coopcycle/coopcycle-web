<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Model\NameTrait;
use AppBundle\Entity\Model\PriceTrait;
use AppBundle\Entity\Menu\MenuItem;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An order item is a line of an order. It includes the quantity and shipping details of a bought offer.
 *
 * @see http://schema.org/OrderItem Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/OrderItem",
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
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
     */
    private $id;

    /**
     * @var MenuItem
     *
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/MenuItem")
     * @Groups({"order_create", "order"})
     */
    private $menuItem;

    /**
     * @var int
     *
     * @Assert\NotBlank()
     * @Groups({"order_create", "order"})
     */
    private $quantity;

    /**
     * @var Order
     */
    private $order;

    /**
     * @Groups({"order"})
     */
    private $modifiers;


     public function __construct()
     {
         $this->modifiers = new ArrayCollection();
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

    /**
     * @return mixed
     */
    public function getModifiers()
    {
        return $this->modifiers;
    }

    /**
     * @param mixed $modifiers
     */
    public function setModifiers($modifiers)
    {
        $this->modifiers = $modifiers;
    }

    public function addModifier(OrderItemModifier $orderedItemModifier)
    {
        $orderedItemModifier->setOrderItem($this);
        $this->modifiers->add($orderedItemModifier);

        return $this;
    }

}
