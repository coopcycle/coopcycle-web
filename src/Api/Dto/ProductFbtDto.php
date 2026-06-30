<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class ProductFbtDto
{
    /** @var array<int, array{product: array, formAction: string}> */
    #[Groups(['product'])]
    public array $items = [];
}
