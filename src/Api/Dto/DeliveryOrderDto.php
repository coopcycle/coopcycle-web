<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class DeliveryOrderDto
{
    #[Groups(['delivery'])]
    public int|null $id = null;

    #[Groups(['delivery', 'delivery_create'])]
    public ArbitraryPriceDto|null $arbitraryPrice = null;

    #[Groups(['delivery', 'delivery_create'])]
    public bool|null $isSavedOrder = null;
}
