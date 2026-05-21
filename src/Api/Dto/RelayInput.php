<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class RelayInput
{
    /**
     * @var Task[]
     */
    #[Assert\NotBlank]
    #[Assert\Count(min: 2, max: 2)]
    #[Groups(['warehouse_relay'])]
    public array $tasks = [];
}
