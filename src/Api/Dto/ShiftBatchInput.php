<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftBatchInput
{
    /**
     * Shifts to create: [{ type, startsAt, endsAt, slots }]
     *
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['shift_batch_create'])]
    #[Assert\NotBlank]
    public array $shifts = [];
}
