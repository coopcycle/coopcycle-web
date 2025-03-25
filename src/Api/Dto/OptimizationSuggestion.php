<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class OptimizationSuggestion
{
    #[Groups(['optimization_suggestions'])]
    public array $gain;

    #[Groups(['optimization_suggestions'])]
    public array $order = [];
}
