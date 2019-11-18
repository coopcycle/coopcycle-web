<?php

namespace AppBundle\Entity\Invoice;

use AppBundle\Entity\Invoice;

class LineItem
{
    /** @var int */
    protected $id;

    /** @var Invoice */
    protected $invoice;

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
    public function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * @param mixed $invoice
     *
     * @return self
     */
    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;

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
     * @param mixed $name
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
