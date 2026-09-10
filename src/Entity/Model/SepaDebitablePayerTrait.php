<?php

namespace AppBundle\Entity\Model;

trait SepaDebitablePayerTrait
{
    protected ?string $stripeCustomerId = null;

    protected ?string $sepaPaymentMethodId = null;

    protected ?string $sepaMandateId = null;

    protected ?string $sepaMandateStatus = null;

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function getSepaPaymentMethodId(): ?string
    {
        return $this->sepaPaymentMethodId;
    }

    public function setSepaPaymentMethodId(?string $sepaPaymentMethodId): static
    {
        $this->sepaPaymentMethodId = $sepaPaymentMethodId;

        return $this;
    }

    public function getSepaMandateId(): ?string
    {
        return $this->sepaMandateId;
    }

    public function setSepaMandateId(?string $sepaMandateId): static
    {
        $this->sepaMandateId = $sepaMandateId;

        return $this;
    }

    public function getSepaMandateStatus(): ?string
    {
        return $this->sepaMandateStatus;
    }

    public function setSepaMandateStatus(?string $sepaMandateStatus): static
    {
        $this->sepaMandateStatus = $sepaMandateStatus;

        return $this;
    }

    public function isSepaMandateActive(): bool
    {
        return 'active' === $this->sepaMandateStatus;
    }
}
