<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Service\SettingsManager;

/**
 * Same two-gate arrangement as the referral and loyalty programs:
 *
 * - MARKETING_AUTOMATION_ENABLED (env var): a deployment-time switch. Off
 *   means the feature doesn't exist at all, not even the admin pages or the
 *   Postmark webhook.
 * - marketing_automation_active (Craue setting, admin-toggleable): a runtime
 *   switch defaulting to off, so turning the env var on can't put a live,
 *   unconfigured campaign tool in front of an admin.
 *
 * Note which gate the webhook uses: isEnvEnabled(), not isActive(). An admin
 * pausing campaigns must not stop bounces and unsubscribes being recorded --
 * those are consent and deliverability facts, and losing them while paused
 * would mean emailing people later who had already opted out.
 */
class MarketingAutomationStatus
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
        return $this->envEnabled && $this->settingsManager->getBoolean('marketing_automation_active');
    }
}
