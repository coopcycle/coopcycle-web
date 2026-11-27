<?php

namespace AppBundle\MessageHandler\GiftCard;

use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Message\GiftCard\GiftCardIssued;
use AppBundle\Service\EmailManager;
use AppBundle\Service\GiftCard\GiftCardStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends a card to whoever it was bought for, and a receipt to whoever paid.
 *
 * Two messages rather than one, because they say different things to
 * different people: the recipient gets the code and the message, and never
 * sees what was paid for it.
 *
 * When there is no recipient address -- somebody buying a card to hand over
 * themselves, or an admin issuing one over the counter -- the card goes to
 * the buyer, so a card is never issued with nobody told about it.
 */
#[AsMessageHandler()]
class SendGiftCardEmails
{
    public function __construct(
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardStatus $giftCardStatus,
        private readonly EmailManager $emailManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GiftCardIssued $event): void
    {
        $giftCard = $this->giftCardRepository->find($event->getGiftCardId());

        if (null === $giftCard) {
            $this->logger->error(sprintf('Gift card #%d not found', $event->getGiftCardId()));

            return;
        }

        $recipientEmail = $giftCard->getRecipientEmail() ?: $giftCard->getPurchaserEmail();

        if (!empty($recipientEmail)) {
            $this->emailManager->sendTo(
                $this->emailManager->createGiftCardMessage($giftCard),
                $recipientEmail
            );
        } else {
            $this->logger->warning(sprintf(
                'Gift card %s has nobody to send to',
                $giftCard->getCode()
            ));
        }

        $purchaserEmail = $giftCard->getPurchaserEmail();

        // Only a real purchase gets a receipt. A card the co-op granted was
        // not sold to anyone, so there is nothing to acknowledge -- and a
        // receipt for a gift would be an odd thing to receive.
        if ($giftCard->isFunded() && !empty($purchaserEmail)) {
            $this->emailManager->sendTo(
                $this->emailManager->createGiftCardReceiptMessage(
                    $giftCard,
                    $this->giftCardStatus->getLegalMention()
                ),
                $purchaserEmail
            );
        }
    }
}
