<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;

/**
 * @ApiResource(
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *     },
 *     "patch"={
 *       "method"="PATCH",
 *     },
 *     "put"={
 *       "method"="PUT",
 *     },
 *   }
 * )
 * @ApiFilter(SearchFilter::class, properties={"store"})
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
