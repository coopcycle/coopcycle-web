<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class CustomerRecommendationsDto
{
    /** @var string[] */
    #[Groups(['customer'])]
    public array $recommendations = [];
}
