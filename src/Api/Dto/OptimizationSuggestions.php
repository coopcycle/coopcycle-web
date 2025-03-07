<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class OptimizationSuggestions
{
    /**
     * @var OptimizationSuggestion[]
     * @Groups({"optimization_suggestions"})
     */
    public array $suggestions = [];
}
