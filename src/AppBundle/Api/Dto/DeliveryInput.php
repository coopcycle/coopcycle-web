<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use AppBundle\Entity\Task;

final class DeliveryInput
{
    /**
     * @var Store|null
     */
    public $store;

    /**
     * @var int|null
     */
    public $weight;

    /**
     * @var Task|null
     */
    public $pickup;

    /**
     * @var Task|null
     */
    public $dropoff;

    public $packages;
}
