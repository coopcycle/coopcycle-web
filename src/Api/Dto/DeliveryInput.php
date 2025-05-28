<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use Symfony\Component\Serializer\Annotation\Groups;

final class DeliveryInput
{

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public Store|null $store = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public TaskInput|null $pickup = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public TaskInput|null $dropoff = null;

    /**
     * @var TaskInput[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $tasks = null;

    /**
     * @deprecated Set weight via TaskInput
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public int|null $weight = null;

    /**
     * @deprecated set packages via TaskInput
     * @var DeliveryFormTaskPackageDto[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $packages = null;

    #[Groups(['delivery_create'])]
    public ArbitraryPriceDto|null $arbitraryPrice = null;

    // used only in a POST request
    #[Groups(['delivery_create'])]
    public string|null $rrule = null;
}
