<?php

namespace AppBundle\Entity\Sylius;

abstract class FrozenOrderLineItem
{
    const TYPE_PRODUCT = 'product';
    const TYPE_SERVICE = 'service';

    /** @var int */
    protected $id;

    /** @var string|null Null on receipts issued before the product/service split */
    protected $type = self::TYPE_PRODUCT;

    /** @var FrozenOrder */
    protected $parent;

    /** @var string */
    protected $name;

    /** @var string|null */
    protected $description;

    /** @var int */
    protected $quantity;

    /** @var int */
    protected $unitPrice;

    /** @var int */
    protected $subtotal;

    /** @var int */
    protected $taxTotal;

    /** @var int */
    protected $total;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     *
     * @return self
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    public function getType(): string
    {
        return $this->type ?? self::TYPE_PRODUCT;
    }

    /**
     * @return self
     */
    public function setType(string $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     *
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param mixed $quantity
     *
     * @return self
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * @param mixed $unitPrice
     *
     * @return self
     */
    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubtotal()
    {
        return $this->subtotal;
    }

    /**
     * @param mixed $subtotal
     *
     * @return self
     */
    public function setSubtotal($subtotal)
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTaxTotal()
    {
        return $this->taxTotal;
    }

    /**
     * @param mixed $taxTotal
     *
     * @return self
     */
    public function setTaxTotal($taxTotal)
    {
        $this->taxTotal = $taxTotal;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param mixed $total
     *
     * @return self
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }
}
