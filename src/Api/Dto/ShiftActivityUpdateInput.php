<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the label and color can be edited after creation — the slug is what's
 * stored on every shift referencing this activity, so it must never change.
 */
final class ShiftActivityUpdateInput
{
    #[Groups(['shift_activity_write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $label = null;

    #[Groups(['shift_activity_write'])]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'This is not a valid hex color.')]
    public ?string $color = null;

    /**
     * Whether couriers assigned to a shift with this activity should be
     * added to the dispatch.
     */
    #[Groups(['shift_activity_write'])]
    public ?bool $addToDispatch = false;
}
