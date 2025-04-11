<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

final class DeliveryFromTasksInput
{

    #[Groups(['delivery_create_from_tasks'])]
    public Store|null $store = null;

    /**
     * @var Task[]|null
     */
    #[Groups(['delivery_create_from_tasks'])]
    public array|null $tasks = null;

}
