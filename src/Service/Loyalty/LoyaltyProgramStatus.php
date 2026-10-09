<?php

namespace AppBundle\Service\Loyalty;

use AppBundle\Service\SettingsManager;

/**
 * Same two-gate arrangement as ReferralProgramStatus:
 *
 * - LOYALTY_PROGRAM_ENABLED (env var): a deployment-time switch. Off means
 *   the feature doesn't exist at all, not even the admin dashboard.
 * - loyalty_program_active (Craue setting, admin-toggleable): a runtime
 *   switch defaulting to *off* even when the env var is on, so turning the
 *   env var on can't put a live, unconfigured program -- no rewards
 *   configured, no points rate set -- in front of customers.
 *
 * isActive() is what any customer-facing or points-granting path checks.
 */
class LoyaltyProgramStatus
{
    public function __construct(
        private readonly bool $envEnabled,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function isEnvEnabled(): bool
    {
        return $this->envEnabled;
    }

    public function isActive(): bool
    {
        return $this->envEnabled && $this->settingsManager->getBoolean('loyalty_program_active');
    }
}
