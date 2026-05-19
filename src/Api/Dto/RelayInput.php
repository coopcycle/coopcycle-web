<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RelayInput
{
    #[Assert\NotBlank]
    #[Assert\Count(min: 2, max: 2)]
    public array $tasks = [];
}
