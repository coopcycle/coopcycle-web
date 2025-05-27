<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Sylius\Cart\SessionStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class ClearSession
{
    public function __construct(private SessionStorage $storage)
    {}

    public function __invoke(CheckoutSucceeded $event)
    {
        $this->storage->remove();
    }
}
