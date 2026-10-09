<?php

namespace AppBundle\MessageHandler\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Entity\Marketing\CampaignRecipient;
use AppBundle\Entity\Marketing\CampaignRecipientRepository;
use AppBundle\Message\Marketing\SendCampaignEmail;
use AppBundle\Service\Marketing\CampaignAudienceResolver;
use AppBundle\Service\Marketing\MarketingMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class SendCampaignEmailHandler
{
    public function __construct(
        private readonly CampaignRecipientRepository $recipientRepository,
        private readonly CampaignAudienceResolver $audienceResolver,
        private readonly MarketingMailer $marketingMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendCampaignEmail $message): void
    {
        $recipient = $this->recipientRepository->find($message->recipientId);

        if (null === $recipient) {
            return;
        }

        // A retried message must not send twice. Anything already resolved
        // -- sent, failed, skipped -- is left alone.
        if (!$recipient->isPending()) {
            return;
        }

        $campaign = $recipient->getCampaign();

        if ($this->wasEmailedByAnotherCampaign($recipient)) {
            // Two campaigns going out at once can both pick up the same
            // person: each worked out its audience before either had sent
            // anything, so neither saw the other.
            $recipient->markAsSkipped('Frequency cap');
            $this->finish($recipient);

            return;
        }

        $email = (new Email())
            ->from(new Address(
                (string) $campaign->getSenderEmail(),
                (string) $campaign->getSenderName()
            ))
            ->to($recipient->getEmail())
            ->subject((string) $campaign->getSubject())
            ->html((string) $campaign->getBodyHtml());

        try {
            $sent = $this->marketingMailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // One address failing shouldn't take the campaign with it, so
            // this is recorded rather than rethrown -- otherwise Messenger
            // would retry the whole message and, for a permanent failure
            // like a malformed address, retry it forever.
            $this->logger->error(sprintf(
                'Campaign #%d failed to send to recipient #%d: %s',
                $campaign->getId(),
                $recipient->getId(),
                $e->getMessage()
            ));

            $recipient->markAsFailed($e->getMessage());
            $this->finish($recipient);

            return;
        }

        if (!$sent) {
            // Suppressed between the audience being worked out and now.
            $recipient->markAsSuppressed();
            $this->finish($recipient);

            return;
        }

        $recipient->markAsSent();
        $this->finish($recipient);
    }

    /**
     * The frequency cap again, against what has actually been sent by now
     * rather than what had been sent when the audience was resolved.
     */
    private function wasEmailedByAnotherCampaign(CampaignRecipient $recipient): bool
    {
        $capDays = $this->audienceResolver->getFrequencyCapDays();

        if ($capDays < 1) {
            return false;
        }

        $recentlyEmailed = $this->recipientRepository->findRecentlyEmailed(
            [$recipient->getEmail()],
            (new \DateTime())->modify(sprintf('-%d days', $capDays))
        );

        return [] !== $recentlyEmailed;
    }

    private function finish(CampaignRecipient $recipient): void
    {
        $this->entityManager->flush();

        $campaign = $recipient->getCampaign();

        // The last worker to finish closes the campaign. Several may decide
        // this at once; they all write the same thing.
        $counts = $this->recipientRepository->countByCampaignAndStatus($campaign);

        if (($counts[CampaignRecipient::STATUS_PENDING] ?? 0) > 0) {
            return;
        }

        if (Campaign::STATUS_SENT === $campaign->getStatus()) {
            return;
        }

        $campaign->setStatus(Campaign::STATUS_SENT);
        $campaign->setSentAt(new \DateTime());

        $this->entityManager->flush();
    }
}
