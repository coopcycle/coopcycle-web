<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class GenerateScheduleInput
{
    /**
     * Any date within the target week; the generator aligns it to the Monday.
     */
    #[Groups(['shift_schedule_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $week = null;
}
