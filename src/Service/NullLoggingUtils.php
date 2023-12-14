<?php

namespace AppBundle\Service;

class NullLoggingUtils extends LoggingUtils
{
    public function getCaller(): string
    {
        return 'function: ? | file: ? | line: ?';
    }

    public function getOrderId($order): string
    {
        return '#1234';
    }
}
