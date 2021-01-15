<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Twig\Extension\RuntimeExtensionInterface;

class SettingResolver implements RuntimeExtensionInterface
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
