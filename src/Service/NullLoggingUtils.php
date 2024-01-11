<?php

namespace AppBundle\Service;

class NullLoggingUtils extends LoggingUtils
{
    public function getCallerAtFrame($frameNumber): string
    {
        return 'function: ? | file: ? | line: ?';
    }

    public function getCaller(): string
    {
        return 'function: ? | file: ? | line: ?';
    }

    public function getOrderId($order): string
    {
        return '#1234';
    }
}
