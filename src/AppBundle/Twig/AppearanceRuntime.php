<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Symfony\Component\Asset\Packages;
use Twig\Extension\RuntimeExtensionInterface;

class AppearanceRuntime implements RuntimeExtensionInterface
{
    private $settingsManager;
    private $packages;

    public function __construct(SettingsManager $settingsManager, Packages $packages)
    {
        $this->settingsManager = $settingsManager;
        $this->packages = $packages;
    }

    public function logo()
    {
    	$customLogo = $this->settingsManager->get('custom_logo');

        $hasCustomLogo = false;
        if (!empty($customLogo)) {
            $hasCustomLogo = filter_var($customLogo, FILTER_VALIDATE_BOOLEAN);
        }

        if ($hasCustomLogo) {
        	return $this->packages->getUrl('images/assets/logo.png');
        }

        return 'https://coopcycle.org/images/logo.svg';
    }
}
