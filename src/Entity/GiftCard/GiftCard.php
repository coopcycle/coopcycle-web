<?php

namespace AppBundle\Entity\GiftCard;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * An amount of money someone bought for someone else to spend on the platform.
 *
 * A gift card is a *means of payment*, never a discount: applying one leaves
 * the order total, the restaurant's take and every tax adjustment exactly as
 * they were, and only changes who hands over the money. That distinction is
 * what keeps the VAT treatment right -- see GiftCardPurchaseManager for why
 * the purchase is deliberately not an Order.
 *
 * The balance here is a cached projection of the ledger
 * ([[GiftCardTransaction]]), kept only so the common "what's left on this
 * card" read doesn't have to sum the ledger every time. It must never be
 * written outside the locking transaction in GiftCardManager, and
 * `coopcycle:gift-cards:check` exists to prove it hasn't drifted.
 */
class GiftCard
{
    /** Row exists, the purchase intent was created, the money has not arrived. */
    public const STATE_PENDING = 'pending';
    /** Paid for (or granted by an admin) and spendable. */
    public const STATE_ACTIVE = 'active';
    /** Spent down to zero. */
    public const STATE_DEPLETED = 'depleted';
    /** Lapsed, and the remaining balance forfeited by the expiry sweep. */
    public const STATE_EXPIRED = 'expired';
    /** Voided by an admin, or refunded in full before any use. */
    public const STATE_CANCELLED = 'cancelled';
    /**
     * The purchase charge is disputed. Redemption is blocked while the
     * dispute runs, which bounds the loss at the card's face value without
     * clawing back orders that were legitimately delivered.
     */
    public const STATE_FROZEN = 'frozen';

    /**
     * Bought with real money: redemptions can be transferred to the
     * restaurant against the purchase charge.
     */
    public const FUNDING_FUNDED = 'funded';
    /**
     * Issued by an admin out of the coop's own pocket (compensation,
     * marketing). There is no purchase charge behind it, so its redemptions
     * settle as untied transfers -- which is correct, but has to be visible.
     */
    public const FUNDING_GRANTED = 'granted';

    protected ?int $id = null;

    protected string $code;

    protected string $currencyCode;

    /**
     * Stripe test-mode and live-mode objects don't mix: a test charge id is
     * not a valid source_transaction for a live transfer. Stamping the mode
     * on the card lets redemption refuse the combination outright instead of
     * failing at settlement, long after the customer has gone.
     */
    protected bool $livemode = false;

    protected int $initialAmount;

    protected int $balance = 0;

    protected string $state = self::STATE_PENDING;

    protected string $funding = self::FUNDING_FUNDED;

    /**
     * Null for a guest purchase. "My gift cards" therefore also matches on
     * email, so someone who buys as a guest and registers later doesn't lose
     * sight of what they bought.
     */
    protected ?Customer $purchaser = null;

    protected ?string $purchaserEmail = null;

    protected ?string $recipientEmail = null;

    protected ?string $recipientName = null;

    protected ?string $message = null;

    /**
     * Null means the card works anywhere on the platform. A restaurant here
     * is purely a redemption rule -- the money still lands with the coop, so
     * a restaurant leaving the platform can never strand a card.
     */
    protected ?LocalBusiness $restaurant = null;

    protected ?string $purchasePaymentIntentId = null;

    protected ?string $purchaseChargeId = null;

    /**
     * Null means this card never lapses. Cards issued before expiry became
     * the default keep that null, and the sweep only looks at cards that
     * have a date -- nobody's card is retroactively given a deadline it was
     * not sold with.
     */
    protected ?\DateTime $expiresAt = null;

    protected ?\DateTime $issuedAt = null;

    protected ?\DateTime $createdAt = null;

    protected ?\DateTime $updatedAt = null;

    protected Collection $transactions;

    protected Collection $purchaseCharges;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->purchaseCharges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function isLivemode(): bool
    {
        return $this->livemode;
    }

    public function setLivemode(bool $livemode): void
    {
        $this->livemode = $livemode;
    }

    public function getInitialAmount(): int
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(int $initialAmount): void
    {
        $this->initialAmount = $initialAmount;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getFunding(): string
    {
        return $this->funding;
    }

    public function setFunding(string $funding): void
    {
        $this->funding = $funding;
    }

    public function isFunded(): bool
    {
        return self::FUNDING_FUNDED === $this->funding;
    }

    public function getPurchaser(): ?Customer
    {
        return $this->purchaser;
    }

    public function setPurchaser(?Customer $purchaser): void
    {
        $this->purchaser = $purchaser;
    }

    public function getPurchaserEmail(): ?string
    {
        return $this->purchaserEmail;
    }

    public function setPurchaserEmail(?string $purchaserEmail): void
    {
        $this->purchaserEmail = $purchaserEmail;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(?string $recipientEmail): void
    {
        $this->recipientEmail = $recipientEmail;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): void
    {
        $this->recipientName = $recipientName;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    public function setRestaurant(?LocalBusiness $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function isPlatformWide(): bool
    {
        return null === $this->restaurant;
    }

    public function getPurchasePaymentIntentId(): ?string
    {
        return $this->purchasePaymentIntentId;
    }

    public function setPurchasePaymentIntentId(?string $purchasePaymentIntentId): void
    {
        $this->purchasePaymentIntentId = $purchasePaymentIntentId;
    }

    public function getPurchaseChargeId(): ?string
    {
        return $this->purchaseChargeId;
    }

    public function setPurchaseChargeId(?string $purchaseChargeId): void
    {
        $this->purchaseChargeId = $purchaseChargeId;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function hasExpired(?\DateTime $now = null): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt <= ($now ?? new \DateTime());
    }

    public function getIssuedAt(): ?\DateTime
    {
        return $this->issuedAt;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, GiftCardTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(GiftCardTransaction $transaction): void
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
        }
    }

    /**
     * @return Collection<int, GiftCardPurchaseCharge>
     */
    public function getPurchaseCharges(): Collection
    {
        return $this->purchaseCharges;
    }

    public function addPurchaseCharge(GiftCardPurchaseCharge $purchaseCharge): void
    {
        if (!$this->purchaseCharges->contains($purchaseCharge)) {
            $this->purchaseCharges->add($purchaseCharge);
            $purchaseCharge->setGiftCard($this);
        }
    }

    // --- State transitions -------------------------------------------------
    //
    // Deliberately plain methods rather than a winzou state machine: the
    // ledger is the source of truth and the state is a derived label, so a
    // second graph would only give the truth somewhere else to live.

    /**
     * Brings the card to life once the money is in (or an admin granted it).
     */
    public function activate(int $amount, ?\DateTime $issuedAt = null): void
    {
        if (self::STATE_PENDING !== $this->state) {
            throw new \LogicException(
                sprintf('Gift card %s cannot be activated from state "%s"', $this->code, $this->state)
            );
        }

        $this->state = self::STATE_ACTIVE;
        $this->balance = $amount;
        $this->issuedAt = $issuedAt ?? new \DateTime();
    }

    /**
     * Whether the card can be spent *right now*. The caller still has to
     * check the amount, the currency, the mode and the restaurant scope --
     * this only answers the question the card can answer about itself.
     */
    public function isRedeemable(?\DateTime $now = null): bool
    {
        return self::STATE_ACTIVE === $this->state
            && $this->balance > 0
            && !$this->hasExpired($now);
    }

    /**
     * Takes money off the card. Only ever called from inside the locked
     * transaction in GiftCardManager, together with the ledger row that
     * explains it.
     */
    public function debit(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Gift card debit must be a positive amount');
        }

        if ($amount > $this->balance) {
            throw new \LogicException(
                sprintf('Gift card %s has %d left, cannot debit %d', $this->code, $this->balance, $amount)
            );
        }

        $this->balance -= $amount;

        if (0 === $this->balance) {
            $this->state = self::STATE_DEPLETED;
        }
    }

    /**
     * Puts money back: a released hold, or a refund.
     *
     * A depleted card comes back to life, because the money really is
     * spendable again. An expired one does not -- the sweep already forfeited
     * the balance, and silently reviving it would contradict the ledger.
     * Refunding onto an expired card is handled by the refund path, which
     * pushes the expiry date out first.
     */
    public function credit(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Gift card credit must be a positive amount');
        }

        if (self::STATE_CANCELLED === $this->state || self::STATE_EXPIRED === $this->state) {
            throw new \LogicException(
                sprintf('Gift card %s cannot be credited from state "%s"', $this->code, $this->state)
            );
        }

        $this->balance += $amount;

        if (self::STATE_DEPLETED === $this->state && $this->balance > 0) {
            $this->state = self::STATE_ACTIVE;
        }
    }

    /**
     * Forfeits whatever is left. Only the expiry sweep calls this, and only
     * when the instance has opted into an expiry policy.
     */
    public function expire(): void
    {
        if (self::STATE_ACTIVE !== $this->state && self::STATE_DEPLETED !== $this->state) {
            throw new \LogicException(
                sprintf('Gift card %s cannot expire from state "%s"', $this->code, $this->state)
            );
        }

        $this->balance = 0;
        $this->state = self::STATE_EXPIRED;
    }

    /**
     * Brings a lapsed card back so a refund has somewhere to land, with a
     * fresh validity period measured from today.
     *
     * Only the refund path calls this. Handing money back onto a card that
     * expired while the order was in flight would be a confiscation with
     * extra steps -- the customer did pay, and the balance is theirs.
     */
    public function revive(?\DateTime $expiresAt = null): void
    {
        if (self::STATE_EXPIRED !== $this->state) {
            return;
        }

        $this->state = self::STATE_ACTIVE;
        $this->expiresAt = $expiresAt;
    }

    public function cancel(): void
    {
        if (self::STATE_CANCELLED === $this->state) {
            return;
        }

        $this->balance = 0;
        $this->state = self::STATE_CANCELLED;
    }

    /**
     * Blocks redemption while a dispute on the purchase charge runs. The
     * balance is left alone: if the dispute is won, unfreezing restores the
     * card exactly as it was.
     */
    public function freeze(): void
    {
        if (self::STATE_ACTIVE === $this->state || self::STATE_DEPLETED === $this->state) {
            $this->state = self::STATE_FROZEN;
        }
    }

    public function unfreeze(): void
    {
        if (self::STATE_FROZEN === $this->state) {
            $this->state = $this->balance > 0 ? self::STATE_ACTIVE : self::STATE_DEPLETED;
        }
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
