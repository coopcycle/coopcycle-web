<?php

namespace AppBundle\Entity\Sylius;

interface PriceInterface
{
    public function getValue(): int;
}
