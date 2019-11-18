<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Invoice\FooterItem;
use AppBundle\Entity\Invoice\LineItem;
use AppBundle\Entity\Invoice\Stakeholder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Invoice
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $number;

    /** @var string */
    protected $orderNumber;

    /** @var \DateTimeInterface */
    protected $issuedAt;

    /** @var int */
    protected $total;

    /** @var Collection|LineItemInterface[] */
    protected $lineItems;

    /** @var Collection|LineItemInterface[] */
    protected $footerItems;

    /** @var Collection|TaxItemInterface[] */
    protected $taxItems;

    /** @var Stakeholder */
    protected $emitter;

    /** @var Stakeholder */
    protected $receiver;

    protected $order;

    public function __construct()
    {
        $this->lineItems = new ArrayCollection();
        $this->footerItems = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param mixed $number
     *
     * @return self
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @param mixed $orderNumber
     *
     * @return self
     */
    public function setOrderNumber($orderNumber)
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIssuedAt()
    {
        return $this->issuedAt;
    }

    /**
     * @param mixed $issuedAt
     *
     * @return self
     */
    public function setIssuedAt($issuedAt)
    {
        $this->issuedAt = $issuedAt;

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

    /**
     * @return mixed
     */
    public function getLineItems(): ?Collection
    {
        return $this->lineItems;
    }

    /**
     * @param mixed $lineItems
     *
     * @return self
     */
    public function setLineItems($lineItems)
    {
        $this->lineItems = $lineItems;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFooterItems(): ?Collection
    {
        return $this->footerItems;
    }

    /**
     * @return mixed
     */
    public function getTaxItems()
    {
        return $this->taxItems;
    }

    /**
     * @param mixed $taxItems
     *
     * @return self
     */
    public function setTaxItems($taxItems)
    {
        $this->taxItems = $taxItems;

        return $this;
    }

    public function addLineItem(LineItem $item)
    {
        $item->setInvoice($this);

        $this->lineItems->add($item);
    }

    public function addFooterItem(FooterItem $item)
    {
        $item->setInvoice($this);

        $this->footerItems->add($item);
    }

    /**
     * @return mixed
     */
    public function getEmitter()
    {
        return $this->emitter;
    }

    /**
     * @param mixed $emitter
     *
     * @return self
     */
    public function setEmitter($emitter)
    {
        $emitter->setInvoice($this);

        $this->emitter = $emitter;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * @param mixed $receiver
     *
     * @return self
     */
    public function setReceiver($receiver)
    {
        $receiver->setInvoice($this);

        $this->receiver = $receiver;

        return $this;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }
}
