<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class PublishWeekInput
{
    #[Groups(['shift_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $week = null;
}
