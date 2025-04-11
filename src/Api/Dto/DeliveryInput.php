<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class DeliveryInput
{

    #[Groups(['pricing_deliveries', 'delivery_create', 'delivery_create_from_tasks'])]
    public Store|null $store = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public TaskInput|null $pickup = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public TaskInput|null $dropoff = null;

    /**
     * @var Task[]|TaskInput[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create', 'delivery_create_from_tasks'])]
    public array|null $tasks = null;

    /**
     * @deprecated Set weight via TaskInput
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public int|null $weight = null;

    /**
     * @deprecated set packages via TaskInput
     * @var TaskPackageInput[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $packages = null;

    #[Groups(['delivery_create'])]
    public ArbitraryPrice|null $arbitraryPrice = null;
}
