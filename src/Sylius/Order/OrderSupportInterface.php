<?php

namespace AppBundle\Sylius\Order;

interface OrderSupportInterface
{
    public function supportsEdenred(): bool;
}
