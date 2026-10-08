<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery\PricingRuleSet;
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

    /**
     * A rule set chosen by a dispatcher to calculate the price,
     * instead of the store's one; ignored for other users
     */
    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    public PricingRuleSet|null $pricingRuleSet = null;

    #[Groups(['delivery_create'])]
    public string|null $paymentMethod = null;

    #[Groups(['delivery_create'])]
    public bool|null $recalculatePrice = null;

    #[Groups(['delivery', 'delivery_create'])]
    public bool|null $isSavedOrder = null;
}
