<?php

namespace AppBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class RequestIdStamp implements StampInterface
{
    public function __construct(
        private string $requestId
    ) {
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
