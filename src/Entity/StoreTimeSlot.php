<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @ApiResource(
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *     }
 *   }
 * )
 */
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
