<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Twig\Extension\RuntimeExtensionInterface;

class StripeResolver implements RuntimeExtensionInterface
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
