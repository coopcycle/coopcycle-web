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
        $companyLogo = $this->settingsManager->get('company_logo');

        if (!empty($companyLogo)) {
            return $this->packages->getUrl(sprintf('images/assets/%s', $companyLogo));
        }
    }
}
