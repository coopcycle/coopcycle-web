<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Filter\DateFilter;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A VroomProblem represents a set of jobs and vehicles to be optimized through the vroom api
 **/

class RoutingProblem
{
    private $tasks;

    private $vehicles;

    public function __construct()
    {
        $this->tasks = [];
        $this->vehicles = [];
    }

    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function addTask(Task $task)
    {
        $this->tasks[] = $task;
    }

    public function removeTask(Task $task)
    {
        $id = $task->getId();
        //TODO - remove by id
    }

    public function getVehicles(): array
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle)
    {
        $this->vehicles[] = $vehicle;
    }

    public function removeVehicle(Vehicle $vehicle)
    {
        $id = $vehicle->getId();
        //TODO remove by id
    }

}
