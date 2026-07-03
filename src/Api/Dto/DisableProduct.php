<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class DisableProduct
{
    #[Groups(['product_disable'])]
    public string $until;
}
