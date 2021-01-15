<?php

namespace AppBundle\Entity\Sylius;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

abstract class FrozenOrder
{
    /** @var int */
    protected $id;

    /** @var \DateTimeInterface */
    protected $issuedAt;

    /** @var Collection|FrozenOrderLineItem[] */
    protected $lineItems;

    /** @var Collection|FrozenOrderFooterItem[] */
    protected $footerItems;

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
     * @return Collection|FrozenOrderLineItem[]
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
     * @return Collection|FrozenOrderFooterItem[]
     */
    public function getFooterItems(): ?Collection
    {
        return $this->footerItems;
    }

    public function addLineItem(FrozenOrderLineItem $item)
    {
        $item->setParent($this);

        $this->lineItems->add($item);
    }

    public function addFooterItem(FrozenOrderFooterItem $item)
    {
        $item->setParent($this);

        $this->footerItems->add($item);
    }
}
