<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShiftSettings;
use AppBundle\Service\SettingsManager;

final class ShiftSettingsProvider implements ProviderInterface
{
    public function __construct(
        private readonly SettingsManager $settingsManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShiftSettings
    {
        $json = $this->settingsManager->get('shift_type_colors');

        $typeColors = [];
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $typeColors = $decoded;
            }
        }

        return new ShiftSettings($typeColors);
    }
}
