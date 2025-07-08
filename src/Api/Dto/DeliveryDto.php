<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Metadata\ApiResource;
use AppBundle\Entity\Store;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    types: ['http://schema.org/ParcelDelivery'],
)]
final class DeliveryDto
{

    #[Groups(['delivery'])]
    public int|null $id = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public Store|null $store = null;

    /**
     * In GET requests: Legacy field for compatibility with the existing API. Use `tasks` instead.
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public TaskDto|null $pickup = null;

    /**
     * In GET requests: Legacy field for compatibility with the existing API. Use `tasks` instead.
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public TaskDto|null $dropoff = null;

    /**
     * @var TaskDto[]|null
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public array|null $tasks = null;

    /**
     * @deprecated Set weight via TaskDto
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public int|null $weight = null;

    /**
     * @deprecated set packages via TaskDto
     * @var TaskPackageDto[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $packages = null;

    // used only in a POST request
    #[Groups(['delivery_create'])]
    public string|null $rrule = null;

    #[Groups(['delivery'])]
    public string|null $trackingUrl = null;

    #[Groups(['delivery', 'delivery_create'])]
    public DeliveryOrderDto|null $order = null;
}
