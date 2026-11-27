<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\GiftCard\GiftCardPurchaseCharge;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Message\GiftCard\GiftCardIssued;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Brings a gift card to life: the one place a card goes from "somebody is
 * trying to buy this" to "this is worth money".
 *
 * Issuing has to be idempotent, because two things race to do it. The
 * purchase page confirms the PaymentIntent and issues the card straight
 * away, so the buyer sees the code immediately; Stripe also sends
 * payment_intent.succeeded, which is the backstop for a buyer who closed the
 * tab. Both paths take a write lock and both check the state first, so
 * whichever arrives second does nothing -- rather than minting a second
 * card, or crediting the first one twice.
 */
class GiftCardIssuer
{
    public function __construct(
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardCodeGenerator $codeGenerator,
        private readonly GiftCardStatus $giftCardStatus,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Creates the card a purchase will pay for, before any money moves.
     *
     * It exists first so its id can be the idempotency key on the Stripe
     * call: a double-submitted form then cannot produce two charges, because
     * both submissions carry the same key.
     */
    public function createPending(
        int $amount,
        string $currencyCode,
        ?Customer $purchaser,
        ?string $purchaserEmail,
        ?string $recipientEmail = null,
        ?string $recipientName = null,
        ?string $message = null,
        ?LocalBusiness $restaurant = null
    ): GiftCard {
        $giftCard = new GiftCard();
        $giftCard->setCode($this->codeGenerator->generate());
        $giftCard->setCurrencyCode(strtoupper($currencyCode));
        $giftCard->setLivemode($this->settingsManager->isStripeLivemode());
        $giftCard->setInitialAmount($amount);
        $giftCard->setPurchaser($purchaser);
        $giftCard->setPurchaserEmail($purchaserEmail);
        $giftCard->setRecipientEmail($recipientEmail);
        $giftCard->setRecipientName($recipientName);
        $giftCard->setMessage($message);

        if (null !== $restaurant && $this->giftCardStatus->isScopingEnabled()) {
            $giftCard->setRestaurant($restaurant);
        }

        $this->entityManager->persist($giftCard);
        $this->entityManager->flush();

        return $giftCard;
    }

    /**
     * The money arrived: activate the card, record what backs it, and send
     * it on its way.
     *
     * Returns the card either way. Being called twice is normal, not an
     * error -- see the class docblock.
     */
    public function issue(GiftCard $giftCard, string $chargeId, int $amountCaptured): GiftCard
    {
        $issued = $this->entityManager->wrapInTransaction(function () use ($giftCard, $chargeId, $amountCaptured) {

            $locked = $this->giftCardRepository->findOneByIdForUpdate($giftCard->getId());

            if (GiftCard::STATE_PENDING !== $locked->getState()) {
                $this->logger->info(sprintf(
                    'Gift card %s already issued, skipping charge %s',
                    $locked->getCode(),
                    $chargeId
                ));

                return null;
            }

            // What Stripe captured is what the card is worth. If they differ
            // -- a partial capture, a changed amount -- the money that
            // actually arrived wins, because that is what the coop can pay
            // restaurants out of.
            $amount = $amountCaptured > 0 ? $amountCaptured : $locked->getInitialAmount();

            if ($amount !== $locked->getInitialAmount()) {
                $this->logger->warning(sprintf(
                    'Gift card %s was sold for %d but %d was captured; issuing for %d',
                    $locked->getCode(),
                    $locked->getInitialAmount(),
                    $amountCaptured,
                    $amount
                ));
                $locked->setInitialAmount($amount);
            }

            $locked->setPurchaseChargeId($chargeId);
            $locked->setExpiresAt($this->giftCardStatus->resolveExpiresAt());
            $locked->activate($amount);

            $this->entityManager->persist(GiftCardTransaction::purchase($locked, $amount));

            // The headroom redemptions will draw on. Transfers to
            // restaurants cite this charge as their source_transaction, so
            // Stripe waits for these funds to settle instead of demanding an
            // available platform balance.
            $locked->addPurchaseCharge(
                new GiftCardPurchaseCharge($locked, $chargeId, $amount, $locked->getCurrencyCode())
            );

            $this->entityManager->flush();

            $this->logger->info(sprintf(
                'Gift card %s issued for %d (charge %s)',
                $locked->getCode(),
                $amount,
                $chargeId
            ));

            return $locked;
        });

        if (null !== $issued) {
            $this->messageBus->dispatch(new GiftCardIssued($issued->getId()));
        }

        return $issued ?? $giftCard;
    }

    /**
     * Issues a card nobody paid for: compensation, a giveaway, a card sold
     * over the counter.
     *
     * Marked `granted`, which is not cosmetic. A granted card has no charge
     * behind it, so when it is spent the transfer to the restaurant cannot
     * cite a source_transaction and comes straight off the platform balance.
     * That is the coop funding the gesture, and the ledger has to be able to
     * say so.
     */
    public function issueGranted(
        int $amount,
        string $currencyCode,
        ?string $recipientEmail = null,
        ?string $recipientName = null,
        ?string $message = null,
        ?LocalBusiness $restaurant = null,
        ?string $reason = null
    ): GiftCard {
        $giftCard = new GiftCard();
        $giftCard->setCode($this->codeGenerator->generate());
        $giftCard->setCurrencyCode(strtoupper($currencyCode));
        $giftCard->setLivemode($this->settingsManager->isStripeLivemode());
        $giftCard->setInitialAmount($amount);
        $giftCard->setFunding(GiftCard::FUNDING_GRANTED);
        $giftCard->setRecipientEmail($recipientEmail);
        $giftCard->setRecipientName($recipientName);
        $giftCard->setMessage($message);
        $giftCard->setExpiresAt($this->giftCardStatus->resolveExpiresAt());

        if (null !== $restaurant && $this->giftCardStatus->isScopingEnabled()) {
            $giftCard->setRestaurant($restaurant);
        }

        $giftCard->activate($amount);

        $this->entityManager->persist($giftCard);
        $this->entityManager->persist(GiftCardTransaction::purchase($giftCard, $amount));
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            'Gift card %s granted for %d%s',
            $giftCard->getCode(),
            $amount,
            null !== $reason ? sprintf(' (%s)', $reason) : ''
        ));

        if (!empty($recipientEmail)) {
            $this->messageBus->dispatch(new GiftCardIssued($giftCard->getId()));
        }

        return $giftCard;
    }
}
