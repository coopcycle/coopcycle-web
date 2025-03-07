<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class OptimizationSuggestion
{
    /**
     * @var array
     * @Groups({"optimization_suggestions"})
     */
    public array $gain;

    /**
     * @var array
     * @Groups({"optimization_suggestions"})
     */
    public array $order = [];
}
