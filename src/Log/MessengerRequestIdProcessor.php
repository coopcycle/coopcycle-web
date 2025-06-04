<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RequestIdStamp;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

#[AsMonologProcessor]
class MessengerRequestIdProcessor extends MessengerStampProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $stamp = $this->getStamp();

        if ($stamp instanceof RequestIdStamp) {
            $record['extra']['request_id'] = $stamp->getValue();
        }

        return $record;
    }
}
