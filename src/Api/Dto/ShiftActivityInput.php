<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftActivityInput
{
    /**
     * The slug is derived from this label (e.g. "Commercial Prospection" ->
     * "commercial-prospection"), never supplied directly.
     */
    #[Groups(['shift_activity_write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $label = null;
}
