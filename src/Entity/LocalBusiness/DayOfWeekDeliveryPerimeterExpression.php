<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\LocalBusiness;

class DayOfWeekDeliveryPerimeterExpression
{
    private int $id;
    private LocalBusiness $restaurant;
    private string $expression;
    private string $daysOfWeek;

    public function getDaysOfWeek(): string
    {
        return $this->daysOfWeek;
    }

    public function setDaysOfWeek(string $daysOfWeek): void
    {
        $this->daysOfWeek = $daysOfWeek;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function setExpression(string $expression): void
    {
        $this->expression = $expression;
    }

    public function setRestaurant(LocalBusiness $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function getRestaurant(): LocalBusiness
    {
        return $this->restaurant;
    }
}
