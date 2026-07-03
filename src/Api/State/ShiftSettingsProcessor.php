<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftSettingsInput;
use AppBundle\Api\Resource\ShiftSettings;
use AppBundle\Service\SettingsManager;

final class ShiftSettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SettingsManager $settingsManager)
    {}

    /**
     * @param ShiftSettingsInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftSettings
    {
        $typeColors = [];
        foreach ($data->typeColors as $type => $color) {
            if (is_string($type) && '' !== $type
                && is_string($color) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                $typeColors[$type] = $color;
            }
        }

        $this->settingsManager->set('shift_type_colors', json_encode($typeColors));
        $this->settingsManager->flush();

        return new ShiftSettings($typeColors);
    }
}
