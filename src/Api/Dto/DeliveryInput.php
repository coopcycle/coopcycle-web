<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class DeliveryInput
{
    /**
     * @var Store|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create_from_tasks'])]
    public $store;

    /**
     * @var int|null
     */
    #[Groups(['delivery_create', 'pricing_deliveries'])]
    public $weight;

    /**
     * @var Task|null
     */
    #[Groups(['delivery_create', 'pricing_deliveries'])]
    public $pickup;

    /**
     * @var Task
     */
    #[Groups(['delivery_create', 'pricing_deliveries'])]
    public $dropoff;

    /**
     * @var array|null
     */
    #[Groups(['pricing_deliveries'])]
    public $packages;

    /**
     * @var Task[]
     */
    #[Groups(['delivery_create', 'delivery_create_from_tasks', 'pricing_deliveries'])]
    public $tasks;
}
