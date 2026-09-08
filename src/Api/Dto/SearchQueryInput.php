<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchQueryInput
{
    #[Groups(['search_query_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public ?string $scope = null;

    /**
     * The raw "key:value" query string, as typed in the search bar - may be
     * empty (e.g. saving "-state:cancelled" alone is fine, but so is saving
     * an otherwise-empty query is not useful, so NotBlank still applies).
     */
    #[Groups(['search_query_create'])]
    #[Assert\NotBlank]
    public ?string $query = null;

    #[Groups(['search_query_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;
}
