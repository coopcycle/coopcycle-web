<?php

namespace AppBundle\Sylius\Payment;

class Context
{
    const METHOD_CARD              = 'card';
    const METHOD_EDENRED           = 'edenred';
    const METHOD_EDENRED_PLUS_CARD = 'edenred+card';

    private $method = self::METHOD_CARD;

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function getMethod()
    {
        return $this->method;
    }
}
