<?php

namespace AppBundle\Service\Marketing;

class MarketingMailerNotConfiguredException extends \RuntimeException
{
    public static function missing(string $what): self
    {
        return new self(sprintf(
            'Marketing email is not configured: %s is missing. Set it under Settings > Marketing emails.',
            $what
        ));
    }
}
