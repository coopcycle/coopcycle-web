<?php

namespace AppBundle\Vroom;

/**
 * a RoutingProblem represents a set of tasks and vehicles
 *
 * @see https://github.com/VROOM-Project/vroom/blob/master/docs/API.md
 */
class RoutingProblem
{
    private $jobs = [];
    private $shipments = [];
    private $vehicles = [];

    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function addJob(Job $job)
    {
        $this->jobs[] = $job;
    }

    public function getShipments(): array
    {
        return $this->shipments;
    }

    public function addShipment(Shipment $shipment)
    {
        $this->shipments[] = $shipment;
    }

    public function getVehicles(): array
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle)
    {
        $this->vehicles[] = $vehicle;
    }
}
