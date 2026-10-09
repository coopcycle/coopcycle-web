<?php

namespace AppBundle\Message\Marketing;

use AppBundle\Entity\Marketing\CampaignRecipient;

/**
 * Carries only the recipient id: by the time a worker picks this up the
 * campaign may have been edited or the address suppressed, and the handler
 * should act on how things are then, not on a copy from when it was queued.
 */
class SendCampaignEmail
{
    public int $recipientId;

    public function __construct(CampaignRecipient $recipient)
    {
        if (null === $recipient->getId()) {
            // Stating the invariant, rather than letting it surface later as
            // a type error: a worker can pick this up the instant it's
            // dispatched, so the row has to exist first.
            throw new \InvalidArgumentException(
                'A campaign recipient must be persisted before it can be queued.'
            );
        }

        $this->recipientId = $recipient->getId();
    }
}
