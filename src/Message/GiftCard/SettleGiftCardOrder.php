<?php

namespace AppBundle\Message\GiftCard;

/**
 * Pay the restaurant for an order a gift card helped pay for.
 *
 * Dispatched rather than done inline, for two reasons. The webhook that
 * usually triggers it can then return 200 immediately, so Stripe never
 * retries because of how long we took -- and a retried webhook is how hub
 * vendors came to be paid twice. And when Stripe says the platform balance
 * is short, the message bus already knows how to back off and try later,
 * which a webhook handler does not.
 */
class SettleGiftCardOrder
{
    public function __construct(
        private readonly int $orderId,
        private readonly ?string $complementChargeId = null,
        private readonly ?int $complementCaptured = null,
    ) {
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getComplementChargeId(): ?string
    {
        return $this->complementChargeId;
    }

    public function getComplementCaptured(): ?int
    {
        return $this->complementCaptured;
    }
}
