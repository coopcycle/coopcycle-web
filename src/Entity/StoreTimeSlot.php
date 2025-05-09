<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
#[ApiResource(operations: [new Get(), new Post(), new GetCollection()])]
class StoreTimeSlot {
    private int $id;
    private Store $store;
    private TimeSlot $timeSlot;
    private int $position;

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setStore(Store $store): void {
        $this->store = $store;
    }

    public function getStre(): Store {
        return $this->store;
    }

    public function setTimeSlot(TimeSlot $timeSlot): void {
        $this->timeSlot = $timeSlot;
    }

    public function getTimeSlot(): TimeSlot {
        return $this->timeSlot;
    }

    public function setPosition(int $position): void {
        $this->position = $position;
    }

    public function getPosition(): int {
        return $this->position;
    }

    public function __toString(): string
    {
        return $this->getTimeSlot()->getName();
    }
}
