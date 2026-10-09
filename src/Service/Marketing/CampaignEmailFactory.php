<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Builds the message a campaign sends.
 *
 * Shared by the real send and the test send, so that "send a test" means
 * exactly the email a customer would get rather than something assembled
 * alongside it that can drift out of step.
 */
class CampaignEmailFactory
{
    public function __construct(private readonly MarketingMailer $marketingMailer)
    {
    }

    /**
     * @throws MarketingMailerNotConfiguredException
     */
    public function create(Campaign $campaign, string $recipientEmail): Email
    {
        return (new Email())
            ->from($this->senderFor($campaign))
            ->to($recipientEmail)
            ->subject((string) $campaign->getSubject())
            ->html((string) $campaign->getBodyHtml());
    }

    /**
     * A campaign snapshots its sender when it goes out, so a sent one is
     * shown and tested as it actually went. A draft hasn't got one yet, and
     * falls back to what is configured now.
     */
    private function senderFor(Campaign $campaign): Address
    {
        if (!empty($campaign->getSenderEmail())) {
            return new Address(
                $campaign->getSenderEmail(),
                (string) $campaign->getSenderName()
            );
        }

        return $this->marketingMailer->getSenderAddress();
    }
}
