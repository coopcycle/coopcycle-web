<?php

namespace AppBundle\Entity;

use Sylius\Component\Order\Model\OrderInterface;

/**
 * A record of money moved from the platform balance to a restaurant's
 * connected account for one order.
 *
 * Until now the platform created transfers and kept nothing: `Stripe\Transfer`
 * objects were made and the returned ids thrown away. That was survivable
 * while only hub orders did it, and it is not survivable now. Without a local
 * record there is no way to reverse a transfer when an order is refunded, no
 * way to notice the same transfer went out twice, and no way to reconcile
 * what left the platform against what came in.
 *
 * The row is written *before* the Stripe call, in state `pending`. Its unique
 * key on (order, idempotencyKey) is the durable guard against double
 * payment -- Stripe's own idempotency keys only last 24 hours, which covers a
 * webhook retry storm but not a replay a week later. A row left `pending` by
 * a crash is resolved by listing transfers for its `transferGroup` and
 * matching, never by transferring again and hoping.
 */
class OrderTransfer
{
    /** Row written, Stripe not called yet (or the answer never came back). */
    public const STATE_PENDING = 'pending';
    public const STATE_SUCCEEDED = 'succeeded';
    public const STATE_FAILED = 'failed';
    public const STATE_REVERSED_PARTIALLY = 'reversed_partially';
    public const STATE_REVERSED = 'reversed';
    /**
     * The restaurant has no connected account, so there is nowhere to send
     * the money. Previously this was a silent `continue` that swallowed the
     * restaurant's takings; now it is a row somebody can be shown.
     */
    public const STATE_BLOCKED_NO_ACCOUNT = 'blocked_no_account';
    /**
     * What was captured doesn't match what the order says it should be, so
     * the amount to transfer is not trustworthy. Recorded rather than guessed.
     */
    public const STATE_MISMATCH = 'mismatch';

    protected ?int $id = null;

    protected OrderInterface $order;

    protected ?LocalBusiness $restaurant = null;

    protected ?string $stripeTransferId = null;

    protected int $amount;

    protected string $currencyCode;

    /**
     * The charge this transfer draws on. Stripe waits for those funds to
     * settle instead of demanding an available platform balance, which is
     * the whole reason gift-card redemption can pay a restaurant out of money
     * taken weeks earlier.
     */
    protected ?string $sourceTransaction = null;

    protected string $transferGroup;

    protected string $idempotencyKey;

    protected string $state = self::STATE_PENDING;

    protected int $reversedAmount = 0;

    protected ?string $failureCode = null;

    protected ?\DateTime $createdAt = null;

    protected ?\DateTime $updatedAt = null;

    public function __construct(
        OrderInterface $order,
        ?LocalBusiness $restaurant,
        int $amount,
        string $currencyCode,
        string $transferGroup,
        string $idempotencyKey,
        ?string $sourceTransaction = null
    ) {
        $this->order = $order;
        $this->restaurant = $restaurant;
        $this->amount = $amount;
        $this->currencyCode = $currencyCode;
        $this->transferGroup = $transferGroup;
        $this->idempotencyKey = $idempotencyKey;
        $this->sourceTransaction = $sourceTransaction;
    }

    public static function transferGroupFor(OrderInterface $order): string
    {
        return sprintf('order_%d', $order->getId());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    public function getStripeTransferId(): ?string
    {
        return $this->stripeTransferId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getSourceTransaction(): ?string
    {
        return $this->sourceTransaction;
    }

    public function getTransferGroup(): string
    {
        return $this->transferGroup;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function isPending(): bool
    {
        return self::STATE_PENDING === $this->state;
    }

    public function isSucceeded(): bool
    {
        return self::STATE_SUCCEEDED === $this->state
            || self::STATE_REVERSED_PARTIALLY === $this->state;
    }

    public function getReversedAmount(): int
    {
        return $this->reversedAmount;
    }

    /** What could still be reversed, if an order is refunded. */
    public function getReversibleAmount(): int
    {
        return max(0, $this->amount - $this->reversedAmount);
    }

    public function getFailureCode(): ?string
    {
        return $this->failureCode;
    }

    public function succeed(string $stripeTransferId): void
    {
        $this->stripeTransferId = $stripeTransferId;
        $this->state = self::STATE_SUCCEEDED;
        $this->failureCode = null;
    }

    public function fail(?string $failureCode = null): void
    {
        $this->state = self::STATE_FAILED;
        $this->failureCode = $failureCode;
    }

    public function block(string $state, ?string $reason = null): void
    {
        $this->state = $state;
        $this->failureCode = $reason;
    }

    public function reverse(int $amount): void
    {
        if ($amount > $this->getReversibleAmount()) {
            throw new \LogicException(sprintf(
                'Transfer %s has %d left to reverse, cannot reverse %d',
                $this->stripeTransferId ?? '(pending)',
                $this->getReversibleAmount(),
                $amount
            ));
        }

        $this->reversedAmount += $amount;
        $this->state = $this->reversedAmount >= $this->amount
            ? self::STATE_REVERSED
            : self::STATE_REVERSED_PARTIALLY;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }
}
