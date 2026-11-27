<?php

namespace AppBundle\Entity\GiftCard;

/**
 * A Stripe charge that put real money behind a gift card, and how much of it
 * is still available to transfer out.
 *
 * Stripe lets several transfers draw on one charge via `source_transaction`,
 * up to the charge amount, and rejects the one that goes over. Since a card
 * is spent across many orders, that headroom has to be tracked here and
 * checked *before* the API call -- otherwise the first thing to notice the
 * overdraft is Stripe, at settlement time, with a restaurant waiting to be
 * paid.
 *
 * Note the limit Stripe applies is the *gross* charge: a 4,50 € charge can
 * source 4,50 € of transfers even though only 4,19 € net landed. The
 * difference comes out of the platform balance, which is the normal cost of
 * doing business and is exactly what the platform commission covers.
 */
class GiftCardPurchaseCharge
{
    protected ?int $id = null;

    protected GiftCard $giftCard;

    protected string $stripeChargeId;

    protected int $amount;

    protected string $currencyCode;

    protected int $amountAllocated = 0;

    protected int $amountRefunded = 0;

    protected ?\DateTime $createdAt = null;

    public function __construct(GiftCard $giftCard, string $stripeChargeId, int $amount, string $currencyCode)
    {
        $this->giftCard = $giftCard;
        $this->stripeChargeId = $stripeChargeId;
        $this->amount = $amount;
        $this->currencyCode = $currencyCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGiftCard(): GiftCard
    {
        return $this->giftCard;
    }

    public function setGiftCard(GiftCard $giftCard): void
    {
        $this->giftCard = $giftCard;
    }

    public function getStripeChargeId(): string
    {
        return $this->stripeChargeId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getAmountAllocated(): int
    {
        return $this->amountAllocated;
    }

    public function getAmountRefunded(): int
    {
        return $this->amountRefunded;
    }

    /**
     * What Stripe would still accept as a transfer against this charge.
     */
    public function getAvailableAmount(): int
    {
        return max(0, $this->amount - $this->amountAllocated - $this->amountRefunded);
    }

    public function allocate(int $amount): void
    {
        if ($amount > $this->getAvailableAmount()) {
            throw new \LogicException(sprintf(
                'Charge %s has %d available, cannot allocate %d',
                $this->stripeChargeId,
                $this->getAvailableAmount(),
                $amount
            ));
        }

        $this->amountAllocated += $amount;
    }

    /**
     * Gives headroom back when a transfer is reversed, so the money can be
     * transferred again if the order is re-settled.
     */
    public function release(int $amount): void
    {
        $this->amountAllocated = max(0, $this->amountAllocated - $amount);
    }

    public function refund(int $amount): void
    {
        $this->amountRefunded += $amount;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }
}
