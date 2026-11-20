<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Entity\OrderTransfer;
use AppBundle\Entity\OrderTransferRepository;
use AppBundle\Entity\Refund;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe;

/**
 * Takes back money already transferred to a restaurant when an order is
 * refunded.
 *
 * The first transfer reversal in the codebase. Until gift cards, the
 * platform created transfers and kept no record of them, so there was
 * nothing to reverse -- a refunded hub order left the restaurant paid and
 * the co-op out of pocket. OrderTransfer rows make this possible; this makes
 * it happen.
 *
 * Who bears a refund is already recorded on the Refund, and that is what
 * decides whether anything is reversed at all:
 *
 * - `platform` -- the co-op's own fault, a late delivery or a mistake of
 *   its own. The restaurant cooked the food and keeps its money; the co-op
 *   carries the loss. Nothing is reversed.
 * - `merchant` -- the restaurant's. Its share comes back, in proportion to
 *   how much of the order was refunded.
 */
class OrderTransferReversal
{
    public function __construct(
        private readonly OrderTransferRepository $orderTransferRepository,
        private readonly StripeManager $stripeManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reverseForRefund(OrderInterface $order, int $refundAmount, string $liableParty): void
    {
        if (Refund::LIABLE_PARTY_MERCHANT !== $liableParty) {
            return;
        }

        $total = $order->getTotal();

        if ($total < 1 || $refundAmount < 1) {
            return;
        }

        $transfers = $this->orderTransferRepository->findByOrder($order);

        // Newest first: the most recent chunk is the one most likely to
        // still be reversible, and reversing it leaves the oldest
        // source-tied transfer -- the one holding a gift card's purchase
        // charge -- alone for as long as possible.
        $transfers = array_reverse($transfers);

        foreach ($transfers as $transfer) {

            if (!$transfer->isSucceeded() || null === $transfer->getStripeTransferId()) {
                continue;
            }

            $share = min(
                (int) ceil($refundAmount * $transfer->getAmount() / $total),
                $transfer->getReversibleAmount()
            );

            if ($share < 1) {
                continue;
            }

            $this->reverse($transfer, $share);
        }

        $this->entityManager->flush();
    }

    private function reverse(OrderTransfer $transfer, int $amount): void
    {
        try {
            $this->stripeManager->createTransferReversal(
                $transfer->getStripeTransferId(),
                $amount,
                sprintf('reverse_%s_%d', $transfer->getIdempotencyKey(), $transfer->getReversedAmount() + $amount)
            );

            $transfer->reverse($amount);

            $this->logger->info(sprintf(
                'Order #%d | reversed %d of transfer %s',
                $transfer->getOrder()->getId(),
                $amount,
                $transfer->getStripeTransferId()
            ));

        } catch (Stripe\Exception\ApiErrorException $e) {
            // Usually `balance_insufficient`: the restaurant has already
            // been paid out and there is nothing left to claw back. Record
            // it for somebody to chase; retrying in a loop would only
            // hammer Stripe to the same end.
            $transfer->fail($e->getStripeCode() ?? 'reversal_failed');

            $this->logger->error(sprintf(
                'Order #%d | could not reverse %d of transfer %s: %s',
                $transfer->getOrder()->getId(),
                $amount,
                $transfer->getStripeTransferId(),
                $e->getMessage()
            ));
        }
    }
}
