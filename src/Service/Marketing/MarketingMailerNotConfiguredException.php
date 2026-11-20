<?php

namespace AppBundle\Service\Marketing;

/**
 * The English message is for logs and stack traces; admins see
 * getTranslationKey()/getTranslationParameters() rendered through the
 * translator, since these bubble up as flash messages.
 */
class MarketingMailerNotConfiguredException extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
    ) {
        parent::__construct($message);
    }

    public static function missingSenderAddress(): self
    {
        return self::missing('the sender address', 'sender_address');
    }

    public static function missingServerToken(): self
    {
        return self::missing('the Postmark server token', 'server_token');
    }

    public static function missingServerTokenOrSenderAddress(): self
    {
        return self::missing('the Postmark server token or sender address', 'any');
    }

    private static function missing(string $what, string $suffix): self
    {
        return new self(
            sprintf(
                'Marketing email is not configured: %s is missing. Set it under Settings > Marketing emails.',
                $what
            ),
            'marketing.mailer.not_configured.' . $suffix
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
