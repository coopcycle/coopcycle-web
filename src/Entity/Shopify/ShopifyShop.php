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

    /**
     * Offline access tokens now expire after an hour. The refresh token (valid
     * 90 days) renews them server-side, with no merchant interaction — which is
     * what lets webhooks and background commands keep working.
     */
    private ?string $refreshToken = null;

    private ?\DateTimeInterface $accessTokenExpiresAt = null;

    private string $webhookSecret;

    private ?string $fulfillmentServiceId = null;

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

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getAccessTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->accessTokenExpiresAt;
    }

    public function setAccessTokenExpiresAt(?\DateTimeInterface $accessTokenExpiresAt): self
    {
        $this->accessTokenExpiresAt = $accessTokenExpiresAt;

        return $this;
    }

    /**
     * Refreshed a minute early so a token cannot expire between the check and
     * the request that uses it. A shop with no expiry recorded predates expiring
     * tokens and is treated as needing a refresh it cannot do — the caller then
     * surfaces Shopify's own error rather than guessing.
     */
    public function isAccessTokenExpired(): bool
    {
        if (null === $this->accessTokenExpiresAt) {
            return false;
        }

        return $this->accessTokenExpiresAt->getTimestamp() - 60 <= time();
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
