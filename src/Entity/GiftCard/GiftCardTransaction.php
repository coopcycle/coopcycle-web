<?php

namespace AppBundle\Entity\GiftCard;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

/**
 * One line of a gift card's ledger, and the only thing allowed to explain a
 * change in its balance.
 *
 * Append-only: nothing is ever deleted and amounts are never rewritten. A
 * released hold is a *new* row putting the money back, not an erased one --
 * which is what makes chargebacks, partial refunds and disputes auditable
 * months later, when the only question anyone can answer is "what does the
 * ledger say".
 *
 * The one mutation allowed is a hold turning into a redemption at capture
 * (@see convertToRedemption). That changes what the row *means*, never what
 * it is worth, so the sum -- and therefore the balance -- is untouched.
 *
 * Amounts are signed, so a plain SUM() over a card reads as its history and
 * must equal [[GiftCard]]::getBalance().
 */
class GiftCardTransaction
{
    /** The card being paid for, or granted. Positive. */
    public const TYPE_PURCHASE = 'purchase';
    /**
     * Money reserved at checkout. Negative, and already out of the balance:
     * a hold is what stops the same money being spent twice while an order
     * is still in flight.
     */
    public const TYPE_HOLD = 'hold';
    /** A hold that became real when the order was fulfilled. Negative. */
    public const TYPE_REDEMPTION = 'redemption';
    /** Money put back: a released hold, or a refunded order. Positive. */
    public const TYPE_REFUND = 'refund';
    /** The remaining balance forfeited at expiry. Negative. */
    public const TYPE_EXPIRY = 'expiry';

    protected ?int $id = null;

    protected GiftCard $giftCard;

    protected string $type;

    protected int $amount;

    protected ?OrderInterface $order = null;

    /**
     * The payment leg this row belongs to. It is what makes releasing a hold
     * idempotent: these events get redelivered, and "has this leg already
     * been put back" is only answerable per leg.
     */
    protected ?PaymentInterface $payment = null;

    /** The hold or redemption a refund row reverses. */
    protected ?self $parent = null;

    protected ?string $stripeTransferId = null;

    /**
     * Holds only. A worker that dies between authorizing and recording the
     * capture leaves money reserved for an order that will never move; this
     * is what lets the reconciliation sweep tell that apart from a hold that
     * is simply still in flight.
     */
    protected ?\DateTime $expiresAt = null;

    protected ?\DateTime $createdAt = null;

    public static function purchase(GiftCard $giftCard, int $amount): self
    {
        return self::create($giftCard, self::TYPE_PURCHASE, abs($amount));
    }

    public static function hold(
        GiftCard $giftCard,
        int $amount,
        OrderInterface $order,
        PaymentInterface $payment,
        ?\DateTime $expiresAt = null
    ): self {
        $entry = self::create($giftCard, self::TYPE_HOLD, -1 * abs($amount));
        $entry->order = $order;
        $entry->payment = $payment;
        $entry->expiresAt = $expiresAt;

        return $entry;
    }

    /**
     * Puts money back on the card, pointing at the row it reverses.
     *
     * Used both for a hold released when an order is cancelled and for a
     * refund of an order that was already fulfilled -- the two are the same
     * movement, and distinguishing them in the ledger would only invite the
     * question of which one a given row is.
     */
    public static function refund(
        GiftCard $giftCard,
        int $amount,
        self $parent,
        ?OrderInterface $order = null,
        ?PaymentInterface $payment = null
    ): self {
        $entry = self::create($giftCard, self::TYPE_REFUND, abs($amount));
        $entry->parent = $parent;
        $entry->order = $order ?? $parent->getOrder();
        $entry->payment = $payment ?? $parent->getPayment();

        return $entry;
    }

    public static function expiry(GiftCard $giftCard, int $amount): self
    {
        return self::create($giftCard, self::TYPE_EXPIRY, -1 * abs($amount));
    }

    private static function create(GiftCard $giftCard, string $type, int $amount): self
    {
        $entry = new self();
        $entry->giftCard = $giftCard;
        $entry->type = $type;
        $entry->amount = $amount;

        $giftCard->addTransaction($entry);

        return $entry;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGiftCard(): GiftCard
    {
        return $this->giftCard;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isHold(): bool
    {
        return self::TYPE_HOLD === $this->type;
    }

    public function isRedemption(): bool
    {
        return self::TYPE_REDEMPTION === $this->type;
    }

    public function isRefund(): bool
    {
        return self::TYPE_REFUND === $this->type;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * The absolute value, for the many places that want to say "12,00 €"
     * without caring which way the money went.
     */
    public function getAbsoluteAmount(): int
    {
        return abs($this->amount);
    }

    /**
     * The order was fulfilled, so the money reserved at checkout is now
     * genuinely spent. Only the type changes: the amount was already out of
     * the balance when the hold was written, and moving it again here would
     * charge the customer twice.
     */
    public function convertToRedemption(): void
    {
        if (!$this->isHold()) {
            throw new \LogicException(
                sprintf('Only a hold can become a redemption, got "%s"', $this->type)
            );
        }

        $this->type = self::TYPE_REDEMPTION;
        $this->expiresAt = null;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function getPayment(): ?PaymentInterface
    {
        return $this->payment;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getStripeTransferId(): ?string
    {
        return $this->stripeTransferId;
    }

    public function setStripeTransferId(?string $stripeTransferId): void
    {
        $this->stripeTransferId = $stripeTransferId;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }
}
