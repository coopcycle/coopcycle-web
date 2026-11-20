<?php

namespace AppBundle\Service\GiftCard;

/**
 * A gift card could not be used, for a reason the customer can be told.
 *
 * The reason code is what the UI translates: "this code doesn't exist" and
 * "this card is for another restaurant" need very different messages, and a
 * single opaque failure would leave the customer with nothing to act on.
 */
class GiftCardException extends \RuntimeException
{
    public const REASON_NOT_FOUND = 'not_found';
    public const REASON_NOT_ACTIVE = 'not_active';
    public const REASON_EXPIRED = 'expired';
    public const REASON_DEPLETED = 'depleted';
    public const REASON_FROZEN = 'frozen';
    public const REASON_CURRENCY_MISMATCH = 'currency_mismatch';
    public const REASON_LIVEMODE_MISMATCH = 'livemode_mismatch';
    public const REASON_WRONG_RESTAURANT = 'wrong_restaurant';
    public const REASON_MULTI_VENDOR = 'multi_vendor';
    public const REASON_MEAL_VOUCHER = 'meal_voucher';
    public const REASON_INSUFFICIENT_BALANCE = 'insufficient_balance';

    public function __construct(
        private readonly string $reason,
        string $message = '',
        private readonly array $context = []
    ) {
        parent::__construct('' !== $message ? $message : sprintf('Gift card cannot be used: %s', $reason));
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * The translation key for this failure, so templates don't each invent
     * their own mapping.
     */
    public function getTranslationKey(): string
    {
        return sprintf('gift_card.error.%s', $this->reason);
    }
}
