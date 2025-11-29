<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskPackageDto
{
    #[Groups(['task', 'delivery'])]
    public string $short_code;

    #[Groups(['task', 'delivery'])]
    public string $name;

    #[Groups(['task', 'delivery', 'pricing_deliveries', 'delivery_create'])]
    public string $type;

    #[Groups(['task', 'delivery'])]
    public int $volume_per_package;

    #[Groups(['task', 'delivery', 'pricing_deliveries', 'delivery_create'])]
    public string|int $quantity;

    /**
     * @var string[]
     */
    #[Groups(['task', 'delivery'])]
    public array $labels = [];

    /**
     * If the package is attached to this task, this will be a single-item array containing this task's URI.
     * If this is a sum of packages from all pickup or dropoff tasks, this will be an array containing the URIs of all pickup or dropoff tasks that have this package(s) attached.
     *
     * @var string[]
     */
    #[Groups(['task', 'delivery'])]
    public array $tasks;
}
