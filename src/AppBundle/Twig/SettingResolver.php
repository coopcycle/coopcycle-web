<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;

class SettingResolver
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function resolveSetting($name)
    {
        return $this->settingsManager->get($name);
    }
}
