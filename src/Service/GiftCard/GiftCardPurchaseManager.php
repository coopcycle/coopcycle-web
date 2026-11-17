<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe;

/**
 * Charges a customer for a gift card.
 *
 * This deliberately does not go through StripeManager. Every method there
 * takes a PaymentInterface and immediately asks it for its Order, then the
 * order's restaurant, fee total and contract -- because every charge it
 * makes is for a delivery being split between a restaurant and the co-op. A
 * gift card purchase is none of those things: there is no restaurant yet, no
 * fee to take, and nobody to transfer to. Threading a nullable order through
 * the busiest money path in the codebase, so that one caller could skip all
 * of it, would make that path harder to read for everyone.
 *
 * So the payload here is short, and the things it leaves out are the point:
 *
 * - no `stripe_account`, so the charge lands on the platform account;
 * - no `application_fee_amount` and no `transfer_data`, so nothing is split
 *   off to anyone.
 *
 * That is "the money always lands with the co-op", written out. It is what
 * makes a card survive the restaurant it was meant for leaving the platform,
 * and -- because the sale is a liability rather than turnover until the card
 * is spent -- it is also what keeps the VAT treatment right.
 *
 * Which is the other reason a purchase is not an Order. Modelling it as one
 * would drag in the vendor requirement, the fee processor and the tax
 * adjustments, and would book the sale as revenue the moment it happened.
 * If someone later proposes "it's just a product, sell it like anything
 * else", this paragraph is the answer.
 */
class GiftCardPurchaseManager
{
    public function __construct(
        private readonly StripeManager $stripeManager,
        private readonly GiftCardIssuer $giftCardIssuer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Creates and confirms the charge for a card that is still `pending`.
     *
     * The card row exists before this is called, and its id is the
     * idempotency key: a double-submitted form reaches Stripe twice with the
     * same key and is charged once.
     */
    public function createIntent(GiftCard $giftCard, string $paymentMethodId): Stripe\PaymentIntent
    {
        $this->stripeManager->setupStripeApi();

        $payload = [
            'amount' => $giftCard->getInitialAmount(),
            'currency' => strtolower($giftCard->getCurrencyCode()),
            'description' => sprintf('Gift card %s', $giftCard->getCode()),
            'payment_method' => $paymentMethodId,
            'confirmation_method' => 'manual',
            'confirm' => true,
            // No capture_method: capture immediately. Orders authorize now
            // and capture when the food is delivered, because there is
            // something still to go wrong. A gift card has nothing to
            // fulfil -- it is worth money the moment it is paid for.
            //
            // It also keeps this charge away from
            // StripeController::handleChargeCaptured(), which assumes every
            // captured charge belongs to an order and would blow up on one
            // that doesn't.
            'metadata' => [
                'gift_card_id' => $giftCard->getId(),
                'gift_card_code' => $giftCard->getCode(),
            ],
        ];

        $this->logger->info(sprintf(
            'Gift card %s | creating payment intent for %d',
            $giftCard->getCode(),
            $giftCard->getInitialAmount()
        ));

        $intent = Stripe\PaymentIntent::create($payload, [
            'idempotency_key' => $this->idempotencyKey($giftCard),
        ]);

        $giftCard->setPurchasePaymentIntentId($intent->id);
        $this->entityManager->flush();

        // Most cards clear here and now: the intent is created with
        // confirm => true, so unless the bank wants a 3-D Secure challenge
        // the money has already arrived by the time this returns. Issue it
        // immediately, or the buyer is shown a code for a card that doesn't
        // exist yet and has to wait for a webhook to catch up.
        $this->issueIfPaid($giftCard, $intent);

        return $intent;
    }

    /**
     * Picks up where the browser left off after a 3-D Secure challenge, and
     * issues the card if the money arrived.
     */
    public function confirm(GiftCard $giftCard): Stripe\PaymentIntent
    {
        $this->stripeManager->setupStripeApi();

        $intent = Stripe\PaymentIntent::retrieve($giftCard->getPurchasePaymentIntentId());

        if (Stripe\PaymentIntent::STATUS_REQUIRES_CONFIRMATION === $intent->status) {
            $intent = $intent->confirm();
        }

        $this->issueIfPaid($giftCard, $intent);

        return $intent;
    }

    /**
     * Issues the card once Stripe says the money is in.
     *
     * Called from the purchase page, so the buyer sees their code without
     * waiting for a webhook, and again from the webhook, for the buyer who
     * closed the tab. GiftCardIssuer takes a lock and checks the state, so
     * whichever gets there second does nothing.
     */
    public function issueIfPaid(GiftCard $giftCard, Stripe\PaymentIntent $intent): bool
    {
        if (Stripe\PaymentIntent::STATUS_SUCCEEDED !== $intent->status) {
            return false;
        }

        $chargeId = $this->resolveChargeId($intent);

        if (null === $chargeId) {
            $this->logger->error(sprintf(
                'Gift card %s | intent %s succeeded with no charge',
                $giftCard->getCode(),
                $intent->id
            ));

            return false;
        }

        $this->giftCardIssuer->issue($giftCard, $chargeId, (int) $intent->amount_received);

        return true;
    }

    /**
     * The charge a transfer will later cite as its source. Newer API
     * versions expose `latest_charge`; older ones only list `charges`, and
     * this instance is pinned to one of those.
     */
    private function resolveChargeId(Stripe\PaymentIntent $intent): ?string
    {
        if (!empty($intent->latest_charge)) {
            return is_string($intent->latest_charge)
                ? $intent->latest_charge
                : $intent->latest_charge->id;
        }

        if (isset($intent->charges) && count($intent->charges->data) > 0) {
            return $intent->charges->data[0]->id;
        }

        return null;
    }

    private function idempotencyKey(GiftCard $giftCard): string
    {
        return sprintf('gift_card_purchase_%d', $giftCard->getId());
    }
}
