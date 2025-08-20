<?php

namespace AppBundle\Api\Dto;

use AppBundle\Validator\Constraints\ManualSupplements as AssertManualSupplements;
use Symfony\Component\Serializer\Annotation\Groups;

#[AssertManualSupplements]
class DeliveryOrderDto
{
    #[Groups(['delivery'])]
    public int|null $id = null;

    /**
     * @var ManualSupplementDto[]|null
     */
    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    public array|null $manualSupplements = null;
    
    #[Groups(['delivery', 'delivery_create'])]
    public ArbitraryPriceDto|null $arbitraryPrice = null;

    #[Groups(['delivery_create'])]
    public bool|null $recalculatePrice = null;

    #[Groups(['delivery', 'delivery_create'])]
    public bool|null $isSavedOrder = null;
}
