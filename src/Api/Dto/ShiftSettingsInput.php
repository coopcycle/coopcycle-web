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
}
