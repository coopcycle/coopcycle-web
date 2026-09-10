<?php

namespace AppBundle\Entity\Model;

/**
 * Implemented by entities that can be charged via Stripe SEPA Direct Debit
 * on the platform account (as opposed to Stripe Connect, through which
 * money flows the other way, to the entity).
 *
 * @see SepaDebitablePayerTrait
 */
interface SepaDebitablePayerInterface
{
    // Deliberately does not declare getId()/getName(): Store and LocalBusiness
    // both have them, untyped, so callers can rely on duck typing for those
    // without forcing an LSP-incompatible return type declaration here.

    public function getStripeCustomerId(): ?string;

    public function setStripeCustomerId(?string $stripeCustomerId): static;

    public function getSepaPaymentMethodId(): ?string;

    public function setSepaPaymentMethodId(?string $sepaPaymentMethodId): static;

    public function getSepaMandateId(): ?string;

    public function setSepaMandateId(?string $sepaMandateId): static;

    public function getSepaMandateStatus(): ?string;

    public function setSepaMandateStatus(?string $sepaMandateStatus): static;

    public function isSepaMandateActive(): bool;
}
