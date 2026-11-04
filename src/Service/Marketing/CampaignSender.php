<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Entity\Marketing\CampaignRecipient;
use AppBundle\Message\Marketing\SendCampaignEmail;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Starts a campaign: works out who it goes to, claims each of them, and
 * hands the actual sending to the queue.
 *
 * Nothing is sent inline. A segment can run to thousands of addresses, and
 * a send that dies halfway through a web request would leave no record of
 * who had already been emailed -- so every recipient is written down first,
 * then sent one message at a time.
 */
class CampaignSender
{
    public function __construct(
        private readonly CampaignAudienceResolver $audienceResolver,
        private readonly MarketingMailer $marketingMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return CampaignAudience what the campaign was sent to
     *
     * @throws CampaignNotSendableException
     * @throws MarketingMailerNotConfiguredException
     */
    public function send(Campaign $campaign): CampaignAudience
    {
        if (!$campaign->isEditable()) {
            throw CampaignNotSendableException::alreadyStarted($campaign);
        }

        if (empty($campaign->getSubject()) || empty($campaign->getBodyHtml())) {
            throw CampaignNotSendableException::incomplete($campaign);
        }

        // Fails here rather than once a few hundred messages are already on
        // the queue, each about to fail identically.
        if (!$this->marketingMailer->isConfigured()) {
            throw MarketingMailerNotConfiguredException::missing('the Postmark server token or sender address');
        }

        $audience = $this->audienceResolver->resolve($campaign->getSegment());

        $sender = $this->marketingMailer->getSenderAddress();
        $campaign->setSenderEmail($sender->getAddress());
        $campaign->setSenderName($sender->getName());

        if ($audience->isEmpty()) {
            // Nobody to send to is a finished campaign, not a stuck one.
            $campaign->setStatus(Campaign::STATUS_SENT);
            $campaign->setSentAt(new \DateTime());
            $this->entityManager->flush();

            $this->logger->info(sprintf(
                'Campaign #%d has no audience in segment "%s", nothing to send',
                $campaign->getId(),
                $campaign->getSegment()
            ));

            return $audience;
        }

        $campaign->setStatus(Campaign::STATUS_SENDING);

        $recipients = [];

        foreach ($audience->recipients as $candidate) {
            $recipient = CampaignRecipient::create(
                $campaign,
                $candidate->email,
                $this->entityManager->getReference(
                    \AppBundle\Entity\Sylius\Customer::class,
                    $candidate->customerId
                )
            );

            $this->entityManager->persist($recipient);
            $recipients[] = $recipient;
        }

        // Everyone is written down before anything is queued: a worker that
        // picks a message up immediately must find its row already there.
        $this->entityManager->flush();

        foreach ($recipients as $recipient) {
            $this->messageBus->dispatch(new SendCampaignEmail($recipient));
        }

        $this->logger->info(sprintf(
            'Campaign #%d queued for %d recipient(s) in segment "%s"',
            $campaign->getId(),
            count($recipients),
            $campaign->getSegment()
        ));

        return $audience;
    }
}
