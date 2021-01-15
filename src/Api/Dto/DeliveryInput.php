<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class DeliveryInput
{
    /**
     * @var Store|null
     * @Groups({"pricing_deliveries"})
     */
    public $store;

    /**
     * @var int|null
     * @Groups({"delivery_create"})
     */
    public $weight;

    /**
     * @var Task|null
     * @Groups({"delivery_create"})
     */
    public $pickup;

    /**
     * @var Task
     * @Groups({"delivery_create"})
     */
    public $dropoff;

    /**
     * @var array|null
     * @Groups({"pricing_deliveries"})
     */
    public $packages;
}
