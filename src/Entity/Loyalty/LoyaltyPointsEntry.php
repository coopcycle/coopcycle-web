<?php

namespace AppBundle\Entity\Loyalty;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * One line of a customer's points ledger. The balance is never stored on the
 * customer: it's the sum of what's left on unexpired CREDIT lines, which
 * keeps "spent" and "expired" from ever being double-counted or racing each
 * other.
 *
 * Only credits carry $remaining. Spending walks the oldest credits first, so
 * points that are about to expire get used before fresher ones, and debits
 * exist purely so the customer can see where their points went.
 */
class LoyaltyPointsEntry
{
    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const TYPE_EXPIRY = 'expiry';

    protected ?int $id = null;

    protected Customer $customer;

    protected string $type;

    /**
     * Signed, so a plain SUM() over the ledger reads as a history: positive
     * for credits, negative for debits.
     */
    protected int $amount;

    /**
     * Credits only: the part not yet spent or expired. This is what the
     * balance is summed from.
     */
    protected int $remaining = 0;

    protected ?OrderInterface $order = null;

    protected ?LoyaltyReward $reward = null;

    protected ?PromotionCouponInterface $coupon = null;

    protected ?\DateTime $createdAt = null;

    /**
     * Credits only. Null means the points never expire.
     */
    protected ?\DateTime $expiresAt = null;

    public static function credit(Customer $customer, int $amount, ?\DateTime $expiresAt = null): self
    {
        $entry = new self();
        $entry->customer = $customer;
        $entry->type = self::TYPE_CREDIT;
        $entry->amount = $amount;
        $entry->remaining = $amount;
        $entry->expiresAt = $expiresAt;

        return $entry;
    }

    public static function debit(Customer $customer, int $amount): self
    {
        $entry = new self();
        $entry->customer = $customer;
        $entry->type = self::TYPE_DEBIT;
        $entry->amount = -1 * abs($amount);

        return $entry;
    }

    /**
     * Records points lapsing. Lapsed credits are already excluded from the
     * balance by their expiry date, so this changes no total -- it's what
     * stops the history leaving the customer to work out for themselves why
     * their balance dropped.
     */
    public static function expiry(Customer $customer, int $amount): self
    {
        $entry = new self();
        $entry->customer = $customer;
        $entry->type = self::TYPE_EXPIRY;
        $entry->amount = -1 * abs($amount);

        return $entry;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isCredit(): bool
    {
        return self::TYPE_CREDIT === $this->type;
    }

    public function isExpiry(): bool
    {
        return self::TYPE_EXPIRY === $this->type;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function consume(int $points): int
    {
        $consumed = min($this->remaining, $points);
        $this->remaining -= $consumed;

        return $consumed;
    }

    public function expire(): void
    {
        $this->remaining = 0;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getReward(): ?LoyaltyReward
    {
        return $this->reward;
    }

    public function setReward(?LoyaltyReward $reward): void
    {
        $this->reward = $reward;
    }

    public function getCoupon(): ?PromotionCouponInterface
    {
        return $this->coupon;
    }

    public function setCoupon(?PromotionCouponInterface $coupon): void
    {
        $this->coupon = $coupon;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }
}
