<?php

namespace AppBundle\Entity\Shopify;

use AppBundle\Entity\Store;
use Gedmo\Timestampable\Traits\Timestampable;

class ShopifyShop
{
    use Timestampable;

    private ?int $id = null;

    private string $shopDomain;

    private string $accessToken;

    private string $webhookSecret;

    private ?string $fulfillmentServiceId = null;

    private ?string $shippingRateHandle = null;

    private ?Store $store = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    public function setShopDomain(string $shopDomain): self
    {
        $this->shopDomain = $shopDomain;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(string $webhookSecret): self
    {
        $this->webhookSecret = $webhookSecret;

        return $this;
    }

    public function getFulfillmentServiceId(): ?string
    {
        return $this->fulfillmentServiceId;
    }

    public function setFulfillmentServiceId(?string $fulfillmentServiceId): self
    {
        $this->fulfillmentServiceId = $fulfillmentServiceId;

        return $this;
    }

    public function getShippingRateHandle(): ?string
    {
        return $this->shippingRateHandle;
    }

    public function setShippingRateHandle(?string $shippingRateHandle): self
    {
        $this->shippingRateHandle = $shippingRateHandle;

        return $this;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setStore(?Store $store): self
    {
        $this->store = $store;

        return $this;
    }
}
