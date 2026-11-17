<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use AppBundle\Entity\GiftCard\GiftCardTransactionRepository;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Payment\MealVoucherPaymentMethods;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

/**
 * Owns the gift card ledger: what a card may pay for, what it has left, and
 * every movement in or out of it.
 *
 * Nothing outside this class writes a balance. Every change goes through a
 * transaction that takes a write lock on the card row first, because the
 * balance is derived from a collection of ledger rows: two checkouts can
 * each append a row without ever touching the same version of the card, so
 * optimistic locking would let both succeed and the card would be overspent.
 */
class GiftCardManager
{
    /**
     * How long a hold stands before the reconciliation sweep treats it as
     * abandoned. Comfortably longer than any order takes to resolve, so a
     * hold is only ever swept when something really did go wrong -- a worker
     * dying between authorizing the payment and recording what became of it.
     */
    private const HOLD_TTL = '+2 days';

    public function __construct(
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardTransactionRepository $transactionRepository,
        private readonly GiftCardStatus $giftCardStatus,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Looks a code up for a customer who just typed it, and refuses it with
     * a reason they can act on.
     *
     * @throws GiftCardException
     */
    public function findForOrder(string $code, OrderInterface $order): GiftCard
    {
        $giftCard = $this->giftCardRepository->findOneByCode($code);

        if (null === $giftCard) {
            throw new GiftCardException(GiftCardException::REASON_NOT_FOUND);
        }

        $this->assertApplicable($giftCard, $order);

        return $giftCard;
    }

    /**
     * Everything that makes a card usable on this order *except* the amount.
     *
     * Kept apart from the balance check on purpose: a card with too little on
     * it is still perfectly valid and simply pays for part of the order,
     * whereas everything here means the card cannot be used at all.
     *
     * @throws GiftCardException
     */
    public function assertApplicable(GiftCard $giftCard, OrderInterface $order, ?string $paymentMethodCode = null): void
    {
        switch ($giftCard->getState()) {
            case GiftCard::STATE_ACTIVE:
                break;
            case GiftCard::STATE_DEPLETED:
                throw new GiftCardException(GiftCardException::REASON_DEPLETED);
            case GiftCard::STATE_EXPIRED:
                throw new GiftCardException(GiftCardException::REASON_EXPIRED);
            case GiftCard::STATE_FROZEN:
                throw new GiftCardException(GiftCardException::REASON_FROZEN);
            default:
                throw new GiftCardException(GiftCardException::REASON_NOT_ACTIVE);
        }

        if ($giftCard->hasExpired()) {
            throw new GiftCardException(GiftCardException::REASON_EXPIRED);
        }

        if (strtoupper($giftCard->getCurrencyCode()) !== strtoupper($this->getOrderCurrency($order))) {
            throw new GiftCardException(GiftCardException::REASON_CURRENCY_MISMATCH);
        }

        // A test-mode charge is not a valid source_transaction for a live
        // transfer, so a card bought in one mode can never settle in the
        // other. Refusing it here beats discovering it at settlement, once
        // the customer has eaten.
        if ($giftCard->isFunded() && $giftCard->isLivemode() !== $this->settingsManager->isStripeLivemode()) {
            throw new GiftCardException(GiftCardException::REASON_LIVEMODE_MISMATCH);
        }

        // Hub orders split one charge across several vendors, rounding each
        // share up. With card-backed money behind the charge, that overage
        // would be drawn against a balance that doesn't cover it.
        if ($order->isMultiVendor()) {
            throw new GiftCardException(GiftCardException::REASON_MULTI_VENDOR);
        }

        if (!$giftCard->isPlatformWide()) {
            $restaurant = $order->getRestaurant();

            if (null === $restaurant || $restaurant->getId() !== $giftCard->getRestaurant()->getId()) {
                throw new GiftCardException(
                    GiftCardException::REASON_WRONG_RESTAURANT,
                    context: ['restaurant' => $giftCard->getRestaurant()->getName()]
                );
            }
        }

        // When the caller knows which method is being chosen, that answers
        // the question on its own. Looking at the order's existing payments
        // instead would be wrong at exactly the moment it matters: a
        // customer switching away from Edenred still has the Edenred leg
        // attached while the order is being re-split, and it is removed a
        // few lines later.
        if (null !== $paymentMethodCode) {
            if ($this->isMealVoucher($paymentMethodCode)) {
                throw new GiftCardException(GiftCardException::REASON_MEAL_VOUCHER);
            }
        } elseif ($this->hasMealVoucherPayment($order)) {
            throw new GiftCardException(GiftCardException::REASON_MEAL_VOUCHER);
        }
    }

    /**
     * Combining a gift card with a meal voucher would double-count the
     * platform fee: the voucher half tells the invoicing code to bill the
     * restaurant for the fee, while the gift-card half already netted it at
     * settlement. Forbidden until the invoicing code can express both at
     * once.
     */
    private function isMealVoucher(string $paymentMethodCode): bool
    {
        return in_array($paymentMethodCode, MealVoucherPaymentMethods::CODES, true);
    }

    private function hasMealVoucherPayment(OrderInterface $order): bool
    {
        foreach ($order->getPayments() as $payment) {
            $code = $payment->getMethod()?->getCode();

            if (null !== $code && $this->isMealVoucher($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How much of this order the attached card can pay for, or zero if it
     * can't pay for any of it.
     *
     * Never throws: this runs inside OrderPaymentProcessor on every cart
     * recalculation, and a card that has quietly become unusable should
     * leave the customer with an ordinary order to pay for, not a broken
     * cart.
     */
    public function getMaxAmount(OrderInterface $order, ?string $paymentMethodCode = null): int
    {
        if (!$this->giftCardStatus->isActive()) {
            return 0;
        }

        $giftCard = $order->getGiftCard();

        if (null === $giftCard) {
            return 0;
        }

        try {
            $this->assertApplicable($giftCard, $order, $paymentMethodCode);
        } catch (GiftCardException $e) {
            $this->logger->info(sprintf(
                'Gift card %s not applicable to order #%s: %s',
                $giftCard->getCode(),
                $order->getId() ?? '(cart)',
                $e->getReason()
            ));

            return 0;
        }

        return min($giftCard->getBalance(), $order->getTotal());
    }

    /**
     * Reserves the payment's amount on the card at checkout.
     *
     * The hold is written before any card is charged, and it is the only
     * thing standing between a single balance and two simultaneous
     * checkouts. If the balance moved since the customer saw the breakdown,
     * this fails -- loudly and before any money moves -- rather than quietly
     * re-splitting the order into amounts they never agreed to.
     *
     * @throws GiftCardException
     */
    public function reserve(PaymentInterface $payment): GiftCardTransaction
    {
        $order = $payment->getOrder();
        $giftCard = $order->getGiftCard();

        if (null === $giftCard) {
            throw new GiftCardException(
                GiftCardException::REASON_NOT_FOUND,
                sprintf('Order #%s has a gift card payment but no gift card', $order->getId())
            );
        }

        return $this->entityManager->wrapInTransaction(function () use ($payment, $order, $giftCard) {

            $locked = $this->giftCardRepository->findOneByIdForUpdate($giftCard->getId());

            // Replay: this leg already holds its money, so hand back the hold
            // rather than taking the amount a second time.
            $existing = $this->transactionRepository->findHoldForPayment($locked, $payment);
            if (null !== $existing) {
                return $existing;
            }

            $this->assertApplicable($locked, $order);

            $amount = $payment->getAmount();

            if ($locked->getBalance() < $amount) {
                throw new GiftCardException(
                    GiftCardException::REASON_INSUFFICIENT_BALANCE,
                    sprintf(
                        'Gift card %s has %d left, order needs %d',
                        $locked->getCode(),
                        $locked->getBalance(),
                        $amount
                    ),
                    ['balance' => $locked->getBalance(), 'amount' => $amount]
                );
            }

            $locked->debit($amount);

            $hold = GiftCardTransaction::hold(
                $locked,
                $amount,
                $order,
                $payment,
                new \DateTime(self::HOLD_TTL)
            );

            $this->entityManager->persist($hold);
            $this->entityManager->flush();

            $this->logger->info(sprintf(
                'Gift card %s | held %d for order #%s | %d left',
                $locked->getCode(),
                $amount,
                $order->getId() ?? '(cart)',
                $locked->getBalance()
            ));

            return $hold;
        });
    }

    /**
     * Turns a hold into a redemption once the order is fulfilled.
     *
     * Only the ledger row's meaning changes. The money left the balance when
     * the hold was written; moving it again here would charge the customer
     * twice for the same meal.
     */
    public function capture(PaymentInterface $payment): ?GiftCardTransaction
    {
        $giftCard = $payment->getOrder()->getGiftCard();

        if (null === $giftCard) {
            return null;
        }

        return $this->entityManager->wrapInTransaction(function () use ($payment, $giftCard) {

            $locked = $this->giftCardRepository->findOneByIdForUpdate($giftCard->getId());

            $hold = $this->transactionRepository->findHoldForPayment($locked, $payment);

            if (null === $hold) {
                // Already captured on an earlier delivery of this event.
                return $this->transactionRepository->findRedemptionForPayment($locked, $payment);
            }

            $hold->convertToRedemption();
            $this->entityManager->flush();

            $this->logger->info(sprintf(
                'Gift card %s | redeemed %d for order #%s',
                $locked->getCode(),
                $hold->getAbsoluteAmount(),
                $payment->getOrder()->getId()
            ));

            return $hold;
        });
    }

    /**
     * Puts money back on the card: a hold released because the order was
     * cancelled, or a refund of one that was already fulfilled.
     *
     * A no-op when this leg has already been put back. Cancellation events
     * get redelivered, and without that check a replay would credit the card
     * a second time -- which is how a customer ends up with more gift card
     * than they paid for.
     */
    public function release(PaymentInterface $payment, ?int $amount = null): ?GiftCardTransaction
    {
        $giftCard = $payment->getOrder()->getGiftCard();

        if (null === $giftCard) {
            return null;
        }

        return $this->entityManager->wrapInTransaction(function () use ($payment, $giftCard, $amount) {

            $locked = $this->giftCardRepository->findOneByIdForUpdate($giftCard->getId());

            if ($this->transactionRepository->hasRefundForPayment($locked, $payment)) {
                return null;
            }

            $parent = $this->transactionRepository->findHoldForPayment($locked, $payment)
                ?? $this->transactionRepository->findRedemptionForPayment($locked, $payment);

            if (null === $parent) {
                $this->logger->warning(sprintf(
                    'Gift card %s | nothing to release for payment #%s',
                    $locked->getCode(),
                    $payment->getId()
                ));

                return null;
            }

            $amount = min($amount ?? $parent->getAbsoluteAmount(), $parent->getAbsoluteAmount());

            if ($amount < 1) {
                return null;
            }

            // A card that lapsed between redemption and refund is pushed out
            // rather than left expired: handing the money back onto a dead
            // card is a confiscation with extra steps.
            if (GiftCard::STATE_EXPIRED === $locked->getState()) {
                $locked->revive($this->giftCardStatus->resolveExpiresAt());
            }

            $locked->credit($amount);

            $refund = GiftCardTransaction::refund($locked, $amount, $parent, $payment->getOrder(), $payment);

            $this->entityManager->persist($refund);
            $this->entityManager->flush();

            $this->logger->info(sprintf(
                'Gift card %s | released %d from order #%s | %d left',
                $locked->getCode(),
                $amount,
                $payment->getOrder()->getId() ?? '(cart)',
                $locked->getBalance()
            ));

            return $refund;
        });
    }

    /**
     * What the ledger says the balance is, which is the real one. The column
     * on the card is a cache of this.
     */
    public function getLedgerBalance(GiftCard $giftCard): int
    {
        return $this->transactionRepository->getLedgerBalance($giftCard);
    }

    private function getOrderCurrency(OrderInterface $order): string
    {
        foreach ($order->getPayments() as $payment) {
            if (null !== $payment->getCurrencyCode()) {
                return $payment->getCurrencyCode();
            }
        }

        return $this->settingsManager->get('currency_code') ?? 'EUR';
    }
}
