<?php

namespace AppBundle\Faker;

use Faker\Provider\Base as BaseProvider;

class PriceProvider extends BaseProvider
{
    private static $cents = [
        '00',
        '50',
        '90',
    ];

    public function price(): int
    {
        return (int) $this->generator->numberBetween(5, 20) . static::randomElement(static::$cents);
    }
}
