<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the label can be edited after creation — the slug is what's stored on
 * every shift referencing this activity, so it must never change.
 */
final class ShiftActivityUpdateInput
{
    #[Groups(['shift_activity_write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $label = null;
}
