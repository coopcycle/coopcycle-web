<?php

declare(strict_types=1);

namespace AppBundle\Message;

class GenerateOrdersForDate
{
    public function __construct(private readonly string $date)
    {}

    public function getDate(): string
    {
        return $this->date;
    }
}
