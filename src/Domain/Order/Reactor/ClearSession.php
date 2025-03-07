<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Sylius\Cart\SessionStorage;

class ClearSession
{
    public function __construct(private SessionStorage $storage)
    {}

    public function __invoke(CheckoutSucceeded $event)
    {
        $this->storage->remove();
    }
}
