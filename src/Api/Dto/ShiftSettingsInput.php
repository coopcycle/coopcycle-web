<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class ShiftSettingsInput
{
    /**
     * @var array<string, string>
     */
    #[Groups(['shift_settings'])]
    public array $typeColors = [];

    /**
     * Deliveries a single courier can complete per hour (drives capacity).
     */
    #[Groups(['shift_settings'])]
    public ?float $throughput = null;

    /**
     * Demand quantile to staff for, e.g 0.8 (higher = fewer shortfalls, more cost).
     */
    #[Groups(['shift_settings'])]
    public ?float $serviceLevel = null;

    /**
     * Legal constraints: {template: string|null, rules: {<override>}}.
     * Omit (null) to leave the current configuration untouched.
     *
     * Deserialized straight from the request body, so the shape is NOT
     * guaranteed — the processor validates template and rules at runtime.
     *
     * @var array<string, mixed>|null
     */
    #[Groups(['shift_settings'])]
    public ?array $legal = null;
}
