<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\Dto\ShiftSettingsInput;
use AppBundle\Api\State\ShiftSettingsProcessor;
use AppBundle\Api\State\ShiftSettingsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'ShiftSettings',
    operations: [
        new Get(
            uriTemplate: '/shift_settings',
            provider: ShiftSettingsProvider::class,
            // Couriers can read the colors (to render their own shift cards),
            // only dispatchers/admins can change them
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
        new Put(
            uriTemplate: '/shift_settings',
            input: ShiftSettingsInput::class,
            processor: ShiftSettingsProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
    ],
    normalizationContext: ['groups' => ['shift_settings']],
    denormalizationContext: ['groups' => ['shift_settings']]
)]
final class ShiftSettings
{
    /**
     * @var array<string, string>
     */
    #[Groups(['shift_settings'])]
    public array $typeColors;

    #[Groups(['shift_settings'])]
    public float $throughput;

    #[Groups(['shift_settings'])]
    public float $serviceLevel;

    /**
     * @param array<string, string> $typeColors
     */
    public function __construct(array $typeColors = [], float $throughput = 0.0, float $serviceLevel = 0.0)
    {
        $this->typeColors = $typeColors;
        $this->throughput = $throughput;
        $this->serviceLevel = $serviceLevel;
    }
}
