<?php

namespace AppBundle\Sylius\Order;

interface OrderSupportInterface
{
    public function supportsGiropay(): bool;

    public function supportsEdenred(): bool;
}
