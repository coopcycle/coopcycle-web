<?php

namespace AppBundle\Twig;

use AppBundle\Service\Loyalty\LoyaltyProgramStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LoyaltyExtension extends AbstractExtension
{
    public function __construct(private readonly LoyaltyProgramStatus $loyaltyProgramStatus)
    {
    }

    public function getFunctions(): array
    {
        return [
            // Distinct from the "loyalty_program_enabled" global (the env-var
            // gate): this reflects the admin-toggleable runtime switch too, so
            // customer-facing nav links hide themselves while the program is
            // deployed but not yet turned on.
            new TwigFunction('loyalty_program_active', [$this->loyaltyProgramStatus, 'isActive']),
        ];
    }
}
