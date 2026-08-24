<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class CopyWeekInput
{
    #[Groups(['shift_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $sourceWeek = null;

    #[Groups(['shift_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $targetWeek = null;
}
