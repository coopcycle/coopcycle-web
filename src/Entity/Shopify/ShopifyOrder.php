<?php

namespace AppBundle\Entity\Shopify;

use AppBundle\Entity\Delivery;
use Gedmo\Timestampable\Traits\Timestampable;

class ShopifyOrder
{
    use Timestampable;

    private ?int $id = null;

    private string $shopifyOrderId;

    private string $shopifyOrderName;

    private ?Delivery $delivery = null;

    private ?ShopifyShop $shop = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShopifyOrderId(): string
    {
        return $this->shopifyOrderId;
    }

    public function setShopifyOrderId(string $shopifyOrderId): self
    {
        $this->shopifyOrderId = $shopifyOrderId;

        return $this;
    }

    public function getShopifyOrderName(): string
    {
        return $this->shopifyOrderName;
    }

    public function setShopifyOrderName(string $shopifyOrderName): self
    {
        $this->shopifyOrderName = $shopifyOrderName;

        return $this;
    }

    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getShop(): ?ShopifyShop
    {
        return $this->shop;
    }

    public function setShop(?ShopifyShop $shop): self
    {
        $this->shop = $shop;

        return $this;
    }
}
