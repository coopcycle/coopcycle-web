<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;

class StripeResolver
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function isLivemode()
    {
        return $this->settingsManager->isStripeLivemode();
    }

    public function canEnableTestmode()
    {
        return $this->settingsManager->canEnableStripeTestmode();
    }

    public function canEnableLivemode()
    {
        return $this->settingsManager->canEnableStripeLivemode();
    }
}
