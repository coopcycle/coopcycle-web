<?php

namespace AppBundle\Entity;

/**
 * a RoutingProblem represents a set of tasks and vehicles
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
        if(($key = array_search($id, $this->tasks)) !== false)
        {
            unset($this->tasks[$key]);
            return true;
        }
        return false;
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
        if(($key = array_search($id, $this->vehicles)) !== false)
        {
            unset($this->vehicles[$key]);
            return true;
        }
        return false;
    }

}
