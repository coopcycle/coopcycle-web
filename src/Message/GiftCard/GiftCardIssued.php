<?php

namespace AppBundle\Message\GiftCard;

/**
 * A gift card has been paid for (or granted) and is ready to be handed over.
 *
 * Carries the id rather than the entity: by the time this is handled the
 * card has been committed, and the handler should work from what is in the
 * database, not from a snapshot taken mid-transaction.
 */
class GiftCardIssued
{
    public function __construct(private readonly int $giftCardId)
    {
    }

    public function getGiftCardId(): int
    {
        return $this->giftCardId;
    }
}
