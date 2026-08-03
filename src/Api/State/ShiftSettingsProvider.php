<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShiftSettings;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\Shift\Compliance\ConstraintTemplates;
use AppBundle\Service\Shift\Compliance\LegalConfig;
use AppBundle\Service\Shift\ScheduleGenerator;

final class ShiftSettingsProvider implements ProviderInterface
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly LegalConfig $legalConfig)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShiftSettings
    {
        $typeColors = $this->decodeJson($this->settingsManager->get('shift_type_colors'));
        $config = $this->decodeJson($this->settingsManager->get('shift_planning_config'));

        return new ShiftSettings(
            $typeColors,
            (float) ($config['throughput'] ?? ScheduleGenerator::DEFAULTS['throughput']),
            (float) ($config['serviceLevel'] ?? ScheduleGenerator::DEFAULTS['serviceLevel']),
            [
                'template' => $this->legalConfig->getTemplate(),
                'rules' => $this->legalConfig->getOverrides(),
            ],
            ConstraintTemplates::TEMPLATES
        );
    }

    private function decodeJson(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
