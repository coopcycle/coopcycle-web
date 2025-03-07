<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RequestIdStamp;
use Monolog\Attribute\AsMonologProcessor;

#[AsMonologProcessor]
class MessengerRequestIdProcessor extends MessengerStampProcessor
{
    public function __invoke(array $record): array
    {
        $stamp = $this->getStamp();

        if ($stamp instanceof RequestIdStamp) {
            $record['extra']['request_id'] = $stamp->getValue();
        }

        return $record;
    }
}
