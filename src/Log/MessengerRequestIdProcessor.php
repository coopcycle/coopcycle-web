<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RequestIdStamp;
use Monolog\Attribute\AsMonologProcessor;

#[AsMonologProcessor]
class MessengerRequestIdProcessor
{
    private ?string $requestId = null;

    public function setStamp(?RequestIdStamp $stamp): void
    {
        $this->requestId = $stamp?->getRequestId();
    }

    public function __invoke(array $record): array
    {
        if ($this->requestId !== null) {
            $record['extra']['request_id'] = $this->requestId;
        }

        return $record;
    }
}
