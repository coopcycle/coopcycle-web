<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftPresetCreateInput
{
    #[Groups(['shift_preset_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Groups(['shift_preset_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public ?string $activity = null;

    /**
     * Wall-clock time of day, "HH:MM" — like ShiftTemplateShift, never a full
     * datetime, since a preset isn't tied to any particular day.
     */
    #[Groups(['shift_preset_create'])]
    #[Assert\NotBlank]
    #[Assert\Regex('/^\d{2}:\d{2}$/')]
    public ?string $startTime = null;

    #[Groups(['shift_preset_create'])]
    #[Assert\NotBlank]
    #[Assert\Regex('/^\d{2}:\d{2}$/')]
    public ?string $endTime = null;

    #[Groups(['shift_preset_create'])]
    #[Assert\Range(min: 1)]
    public int $slots = 1;

    #[Groups(['shift_preset_create'])]
    #[Assert\Range(min: 0)]
    public int $breakMinutes = 0;

    #[Groups(['shift_preset_create'])]
    #[Assert\Length(max: 65535)]
    public ?string $comment = null;

    /**
     * @var string[] Skill IRIs
     */
    #[Groups(['shift_preset_create'])]
    public array $requiredSkills = [];
}
