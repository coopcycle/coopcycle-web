<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Twig\Extension\RuntimeExtensionInterface;
use AppBundle\Utils\GeoUtils;

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

    public function getBoundingRect(): string
    {
        $latlng = $this->settingsManager->get('latlng');

        [ $lat, $lng ] = explode(',', $latlng);

        return implode(',', GeoUtils::getViewbox($lat, $lng, 15));
    }
}
