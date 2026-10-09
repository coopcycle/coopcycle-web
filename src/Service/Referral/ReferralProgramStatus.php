<?php

namespace AppBundle\Service\Referral;

use AppBundle\Service\SettingsManager;

/**
 * Two independent gates control the referral program:
 *
 * - REFERRAL_PROGRAM_ENABLED (env var, $envEnabled here): a deployment-time
 *   switch. Off means the feature doesn't exist at all -- not even the admin
 *   dashboard. This is what lets an instance ship the feature without any
 *   trace of it until someone opts in at the infra level.
 * - referral_program_active (Craue setting, admin-toggleable from
 *   /admin/referral-program): a runtime switch, defaulting to *off* even
 *   when the env var is on, so enabling the env var alone can never surprise
 *   an admin with a live, unconfigured referral program -- they still have
 *   to deliberately flip this on once they've set up levels/welcome coupon.
 *
 * isActive() is the one any customer-facing or reward-granting code path
 * should check. The env var alone (still exposed directly where it's bound,
 * e.g. the admin controller/nav link) is enough to let an admin reach the
 * dashboard and turn the program on in the first place.
 */
class ReferralProgramStatus
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
        return $this->envEnabled && $this->settingsManager->getBoolean('referral_program_active');
    }
}
