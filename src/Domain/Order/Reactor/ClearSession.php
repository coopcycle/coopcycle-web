<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use Symfony\Component\HttpFoundation\RequestStack;

class ClearSession
{
    private $requestStack;
    private $sessionKeyName;

    public function __construct(
        RequestStack $requestStack,
        $sessionKeyName)
    {
        $this->requestStack = $requestStack;
        $this->sessionKeyName = $sessionKeyName;
    }

    public function __invoke(CheckoutSucceeded $event)
    {
        $this->requestStack->getSession()->remove($this->sessionKeyName);
    }
}
