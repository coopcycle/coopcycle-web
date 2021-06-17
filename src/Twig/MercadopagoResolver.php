<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Twig\Extension\RuntimeExtensionInterface;

class MercadopagoResolver implements RuntimeExtensionInterface
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function canEnableTestmode()
    {
        return $this->settingsManager->canEnableMercadopagoTestmode();
    }

    public function canEnableLivemode()
    {
        return $this->settingsManager->canEnableMercadopagoLivemode();
    }
}
