<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;

class CampaignNotSendableException extends \RuntimeException
{
    public static function alreadyStarted(Campaign $campaign): self
    {
        return new self(sprintf(
            'Campaign #%d is "%s" and has already been sent or is being sent.',
            $campaign->getId(),
            $campaign->getStatus()
        ));
    }

    public static function incomplete(Campaign $campaign): self
    {
        return new self(sprintf(
            'Campaign #%d needs both a subject and a body before it can be sent.',
            $campaign->getId()
        ));
    }
}
