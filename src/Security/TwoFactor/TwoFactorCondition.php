<?php

namespace AppBundle\Security\TwoFactor;

use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;

class TwoFactorCondition implements TwoFactorConditionInterface
{
    public function __construct(private bool $isDemo) {}

    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        return !$this->isDemo;
    }
}
