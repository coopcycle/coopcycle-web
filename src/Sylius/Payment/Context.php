<?php

namespace AppBundle\Sylius\Payment;

class Context
{
    private $method = 'CARD';

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function hasMethod(): bool
    {
        return null !== $this->method;
    }
}
