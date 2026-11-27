<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCardPurchaseCharge;
use AppBundle\Entity\OrderTransfer;
use AppBundle\Entity\OrderTransferRepository;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe;

/**
 * Pays the restaurant for an order a gift card helped pay for.
 *
 * An ordinary order is split by Stripe Connect at the moment of the charge:
 * the restaurant's share is routed straight to their account and the co-op's
 * commission is taken as an application fee. An order part-paid by a gift
 * card cannot work that way, because part of the money was taken weeks
 * earlier, for something that wasn't an order yet.
 *
 * So the whole charge stays on the platform account and the restaurant is
 * paid afterwards by transfer. That is not a new mechanism -- it is exactly
 * what hub orders have always done. A gift-card order is simply a
 * single-vendor order settled the hub way, which is why there is one rail
 * here and not two.
 *
 * The arithmetic is arranged so the restaurant ends up with precisely what
 * it would have earned on an ordinary order, and the co-op keeps precisely
 * its usual commission. @see resolveTransferAmount().
 */
class GiftCardSettlement
{
    public function __construct(
        private readonly OrderTransferRepository $orderTransferRepository,
        private readonly StripeManager $stripeManager,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string|null $complementChargeId the card charge for the part the
     *                                        gift card didn't cover, if there was one
     * @param int|null    $complementCaptured what Stripe actually captured on it
     */
    public function settle(
        OrderInterface $order,
        ?string $complementChargeId = null,
        ?int $complementCaptured = null
    ): void {
        if (!$order->hasGiftCardPayment()) {
            return;
        }

        if ($this->orderTransferRepository->hasSucceededTransfer($order)) {
            $this->logger->info(sprintf('Order #%d already settled, skipping', $order->getId()));

            return;
        }

        $restaurant = $order->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        $currency = $this->resolveCurrency($order);
        $transferGroup = OrderTransfer::transferGroupFor($order);

        $livemode = $this->settingsManager->isStripeLivemode();
        $stripeAccount = $restaurant->getStripeAccount($livemode);

        if (null === $stripeAccount) {
            // createTransfersForHub quietly skips this case, which means the
            // restaurant's takings simply stay with the platform and nobody
            // ever finds out. Record it instead, so somebody can.
            $this->record($order, OrderTransfer::STATE_BLOCKED_NO_ACCOUNT, 0, $currency, $transferGroup,
                sprintf('settle_%d_no_account', $order->getId()), null, 'no_stripe_account');

            $this->logger->error(sprintf(
                'Order #%d | restaurant #%d has no Stripe account, cannot settle',
                $order->getId(),
                $restaurant->getId()
            ));

            return;
        }

        // What was taken has to add up to what the order says it costs,
        // otherwise the amount below is paying out money that may not be
        // there. Better to stop and be asked about it.
        if (null !== $complementCaptured) {
            $collected = $order->getGiftCardTotal() + $complementCaptured;

            if ($collected !== $order->getTotal()) {
                $this->record($order, OrderTransfer::STATE_MISMATCH, 0, $currency, $transferGroup,
                    sprintf('settle_%d_mismatch', $order->getId()), null, sprintf(
                        'collected %d, order total %d', $collected, $order->getTotal()
                    ));

                $this->logger->error(sprintf(
                    'Order #%d | collected %d but the order totals %d, refusing to settle',
                    $order->getId(),
                    $collected,
                    $order->getTotal()
                ));

                return;
            }
        }

        $remaining = $this->resolveTransferAmount($order, $restaurant);

        if ($remaining < 1) {
            $this->logger->info(sprintf('Order #%d | nothing to transfer', $order->getId()));

            return;
        }

        $destination = $stripeAccount->getStripeUserId();
        $index = 0;

        foreach ($this->resolveSources($order, $complementChargeId, $complementCaptured) as [$chargeId, $available, $purchaseCharge]) {

            if ($remaining < 1) {
                break;
            }

            $chunk = min($available, $remaining);

            if ($chunk < 1) {
                continue;
            }

            $transfer = $this->send(
                $order, $destination, $chunk, $currency, $chargeId, $transferGroup,
                sprintf('settle_%d_%d', $order->getId(), $index++)
            );

            if (null === $transfer) {
                // Stripe refused. Stop rather than trying the next source:
                // whatever went wrong will very likely go wrong again, and
                // the retry has a record to work from.
                return;
            }

            if (null !== $purchaseCharge) {
                $purchaseCharge->allocate($chunk);
            }

            $remaining -= $chunk;
        }

        if ($remaining > 0) {
            // Nothing left to draw on. Correct for a card the co-op granted
            // -- there was never a charge behind it, and the co-op is
            // funding the gesture out of its own balance. On a card that was
            // actually paid for it means the ledger and Stripe disagree,
            // which is worth knowing about.
            $this->logger->warning(sprintf(
                'Order #%d | transferring %d with no source charge',
                $order->getId(),
                $remaining
            ));

            $this->send(
                $order, $destination, $remaining, $currency, null, $transferGroup,
                sprintf('settle_%d_untied', $order->getId())
            );
        }

        $this->entityManager->flush();
    }

    /**
     * What the restaurant is owed.
     *
     * `getTransferAmount()` is the restaurant's share of the order, computed
     * the same way it is for every other order -- the total less the co-op's
     * commission. The deduction on top is the Stripe processing fee, and it
     * is there to keep the contract meaning what it says.
     *
     * On a `restaurantPaysStripeFee` contract an ordinary order is a direct
     * charge on the restaurant's own account, so Stripe takes its processing
     * fee out of *their* balance. Moving the charge to the platform would
     * silently move that cost to the co-op. Deducting it here puts it back
     * where the contract puts it, and both sides end up with exactly the
     * same number as on an ordinary order.
     */
    private function resolveTransferAmount(OrderInterface $order, $restaurant): int
    {
        $amount = $order->getTransferAmount($restaurant);

        if ($restaurant->getContract()?->isRestaurantPaysStripeFee()) {
            $amount -= $order->getStripeFeeTotal();
        }

        return $amount;
    }

    /**
     * The charges this settlement can draw on, best first, each with the
     * amount it can still give.
     *
     * The complement comes first: it is this order's own money, freshest and
     * exactly scoped. Then the gift card's purchase charges, oldest first,
     * so the money that has been sitting with the co-op longest is the money
     * that moves.
     *
     * Every source is capped by what it holds. Stripe will not let the
     * transfers drawn on a charge exceed the charge, and finding that out
     * from a rejection -- with a restaurant waiting to be paid -- is a poor
     * substitute for knowing it here.
     *
     * @return iterable<array{0: ?string, 1: int, 2: ?GiftCardPurchaseCharge}>
     */
    private function resolveSources(
        OrderInterface $order,
        ?string $complementChargeId,
        ?int $complementCaptured
    ): iterable {
        if (null !== $complementChargeId && null !== $complementCaptured && $complementCaptured > 0) {
            yield [$complementChargeId, $complementCaptured, null];
        }

        $giftCard = $order->getGiftCard();

        if (null === $giftCard) {
            return;
        }

        foreach ($giftCard->getPurchaseCharges() as $purchaseCharge) {
            if ($purchaseCharge->getAvailableAmount() > 0) {
                yield [
                    $purchaseCharge->getStripeChargeId(),
                    $purchaseCharge->getAvailableAmount(),
                    $purchaseCharge,
                ];
            }
        }
    }

    /**
     * Writes the row, then makes the call.
     *
     * That order matters. The row is the durable record that this transfer
     * was attempted: if the process dies before Stripe answers, it is left
     * `pending` and the reconciliation sweep can go and look at what
     * actually happened, instead of a later run cheerfully paying again.
     */
    private function send(
        OrderInterface $order,
        string $destination,
        int $amount,
        string $currency,
        ?string $sourceTransaction,
        string $transferGroup,
        string $idempotencyKey
    ): ?OrderTransfer {
        $existing = $this->orderTransferRepository->findOneByIdempotencyKey($order, $idempotencyKey);

        if (null !== $existing && !$existing->isPending()) {
            return $existing->isSucceeded() ? $existing : null;
        }

        $transfer = $existing ?? $this->record(
            $order, OrderTransfer::STATE_PENDING, $amount, $currency,
            $transferGroup, $idempotencyKey, $sourceTransaction
        );

        try {
            $stripeTransfer = $this->stripeManager->createTransfer(
                destination: $destination,
                amount: $amount,
                currency: $currency,
                sourceTransaction: $sourceTransaction,
                transferGroup: $transferGroup,
                idempotencyKey: $idempotencyKey,
            );

            $transfer->succeed($stripeTransfer->id);
            $this->entityManager->flush();

            return $transfer;

        } catch (Stripe\Exception\ApiErrorException $e) {
            $transfer->fail($e->getStripeCode() ?? 'api_error');
            $this->entityManager->flush();

            $this->logger->error(sprintf(
                'Order #%d | transfer of %d failed: %s',
                $order->getId(),
                $amount,
                $e->getMessage()
            ));

            return null;
        }
    }

    private function record(
        OrderInterface $order,
        string $state,
        int $amount,
        string $currency,
        string $transferGroup,
        string $idempotencyKey,
        ?string $sourceTransaction = null,
        ?string $failureCode = null
    ): OrderTransfer {
        $transfer = new OrderTransfer(
            $order,
            $order->getRestaurant(),
            $amount,
            $currency,
            $transferGroup,
            $idempotencyKey,
            $sourceTransaction
        );

        if (OrderTransfer::STATE_PENDING !== $state) {
            $transfer->block($state, $failureCode);
        }

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        return $transfer;
    }

    private function resolveCurrency(OrderInterface $order): string
    {
        foreach ($order->getPayments() as $payment) {
            if (null !== $payment->getCurrencyCode()) {
                return $payment->getCurrencyCode();
            }
        }

        return $this->settingsManager->get('currency_code') ?? 'EUR';
    }
}
