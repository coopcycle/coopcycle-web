<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

final class RelayInput
{
    /**
     * @var Task[]
     */
    #[Assert\NotBlank]
    #[Assert\Count(min: 2, max: 2)]
    public array $tasks = [];
}
