<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ClearSession
{
    private $session;
    private $sessionKeyName;

    public function __construct(
        SessionInterface $session,
        $sessionKeyName)
    {
        $this->session = $session;
        $this->sessionKeyName = $sessionKeyName;
    }

    public function __invoke(CheckoutSucceeded $event)
    {
        $this->session->remove($this->sessionKeyName);
    }
}
