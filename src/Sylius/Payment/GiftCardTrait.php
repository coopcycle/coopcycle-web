<?php

namespace AppBundle\Sylius\Payment;

/**
 * Gift-card details on a Payment, kept in the `details` JSON column the same
 * way EdenredTrait and StripeTrait keep theirs.
 *
 * The code is copied onto the payment rather than read back through the
 * order's gift card, so a leg still says what paid for it even if the card
 * is later detached from the cart or cancelled. Refunds and support queries
 * both need that long after checkout.
 */
trait GiftCardTrait
{
    public function setGiftCardCode(?string $code): void
    {
        $this->details = array_merge($this->details, [
            'gift_card_code' => $code,
        ]);
    }

    public function getGiftCardCode(): ?string
    {
        return $this->details['gift_card_code'] ?? null;
    }

    public function setGiftCardTransactionId(?int $transactionId): void
    {
        $this->details = array_merge($this->details, [
            'gift_card_transaction_id' => $transactionId,
        ]);
    }

    public function getGiftCardTransactionId(): ?int
    {
        return $this->details['gift_card_transaction_id'] ?? null;
    }
}
