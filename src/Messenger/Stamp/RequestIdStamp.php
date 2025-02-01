<?php

namespace AppBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class RequestIdStamp implements StampInterface
{
    public function __construct(
        private readonly string $value
    )
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
