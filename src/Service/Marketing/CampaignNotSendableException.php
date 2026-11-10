<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;

/**
 * The English message is for logs and stack traces; admins see
 * getTranslationKey()/getTranslationParameters() rendered through the
 * translator, since these bubble up as flash messages.
 */
class CampaignNotSendableException extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
    ) {
        parent::__construct($message);
    }

    public static function alreadyStarted(Campaign $campaign): self
    {
        return new self(
            sprintf(
                'Campaign #%d is "%s" and has already been sent or is being sent.',
                $campaign->getId(),
                $campaign->getStatus()
            ),
            'marketing.campaign.send.already_started'
        );
    }

    public static function incomplete(Campaign $campaign): self
    {
        return new self(
            sprintf(
                'Campaign #%d needs both a subject and a body before it can be sent.',
                $campaign->getId()
            ),
            'marketing.campaign.send.incomplete'
        );
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }
}
