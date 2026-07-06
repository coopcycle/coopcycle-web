<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftSettingsInput;
use AppBundle\Api\Resource\ShiftSettings;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\Shift\ScheduleGenerator;

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

        // Merge the tunables into the schedule-generation config blob, keeping any
        // existing keys not exposed by this endpoint (lookback, hours, min/max)
        $config = $this->currentConfig();

        if (null !== $data->throughput) {
            // Clamp to a sane range: at least a fraction of a delivery/hour
            $config['throughput'] = max(0.1, min(20.0, $data->throughput));
        }
        if (null !== $data->serviceLevel) {
            $config['serviceLevel'] = max(0.5, min(0.99, $data->serviceLevel));
        }

        $this->settingsManager->set('shift_planning_config', json_encode($config));
        $this->settingsManager->flush();

        return new ShiftSettings(
            $typeColors,
            (float) ($config['throughput'] ?? ScheduleGenerator::DEFAULTS['throughput']),
            (float) ($config['serviceLevel'] ?? ScheduleGenerator::DEFAULTS['serviceLevel'])
        );
    }

    private function currentConfig(): array
    {
        $json = $this->settingsManager->get('shift_planning_config');
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
