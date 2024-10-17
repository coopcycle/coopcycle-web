<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Trailer;
use AppBundle\Entity\Vehicle;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

class MyTaskListDto
{
    #[Groups(["task_list"])]
    public readonly int $id;

    #[Groups(["task_list"])]
    public readonly DateTime $createdAt;

    #[Groups(["task_list"])]
    public readonly DateTime $updatedAt;

    #[Groups(["task_list"])]
    public readonly DateTime $date;

    #[Groups(["task_list"])]
    public readonly string $username;

    /**
     * @var MyTaskDto[]
     */
    #[Groups(["task_list"])]
    public readonly array $items;

    #[Groups(["task_list"])]
    public readonly int $distance;

    #[Groups(["task_list"])]
    public readonly int $duration;

    #[Groups(["task_list"])]
    public readonly string $polyline;

    //TODO
    #[Groups(["task_list"])]
    public readonly ?Vehicle $vehicle;

    //TODO
    #[Groups(["task_list"])]
    public readonly ?Trailer $trailer;

    /**
     * @param int $id
     * @param DateTime $createdAt
     * @param DateTime $updatedAt
     * @param DateTime $date
     * @param string $username
     * @param MyTaskDto[] $items
     * @param int $distance
     * @param int $duration
     * @param string $polyline
     * @param Vehicle|null $vehicle
     * @param Trailer|null $trailer
     */
    public function __construct(
        int $id,
        DateTime $createdAt,
        DateTime $updatedAt,
        DateTime $date,
        string $username,
        array $items,
        int $distance,
        int $duration,
        string $polyline,
        ?Vehicle $vehicle,
        ?Trailer $trailer)
    {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->date = $date;
        $this->username = $username;
        $this->items = $items;
        $this->distance = $distance;
        $this->duration = $duration;
        $this->polyline = $polyline;
        $this->vehicle = $vehicle;
        $this->trailer = $trailer;
    }
}
