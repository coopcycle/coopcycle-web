<?php

namespace AppBundle\Twig;

use AppBundle\Service\Referral\ReferralProgramStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ReferralExtension extends AbstractExtension
{
    public function __construct(private readonly ReferralProgramStatus $referralProgramStatus)
    {
    }

    public function getFunctions(): array
    {
        return [
            // Distinct from the "referral_program_enabled" global (the env-var
            // gate): this reflects the admin-toggleable runtime switch too, so
            // customer-facing nav links can hide themselves while the program
            // is deployed but not yet turned on.
            new TwigFunction('referral_program_active', [$this->referralProgramStatus, 'isActive']),
        ];
    }
}
