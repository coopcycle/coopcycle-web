<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * An order item is a line of an order. It includes the quantity and shipping details of a bought offer.
 *
 * @see http://schema.org/OrderItem Documentation on Schema.org
 *
 * @ORM\Entity
 * @ApiResource(iri="http://schema.org/OrderItem")
 */
class OrderItem
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
     * @var Product
     * @Groups({"order"})
     * @ORM\ManyToOne(targetEntity="Product")
     */
    private $product;

    /**
     * @var int
     *
     * @Groups({"order"})
     * @ORM\Column(type="integer")
     */
    private $quantity;

    /**
     * @var Order
     *
     * @ORM\ManyToOne(targetEntity="Order", inversedBy="orderedItem")
     * @ORM\JoinColumn(nullable=false)
     */
    private $order;

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
     * Sets product.
     *
     * @param Product $product
     *
     * @return $this
     */
    public function setProduct($product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Gets product.
     *
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
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
