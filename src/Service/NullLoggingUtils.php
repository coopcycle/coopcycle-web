<?php

namespace AppBundle\Service;

class NullLoggingUtils extends LoggingUtils
{
    public function getBacktrace(int $firstFrame = 2, int $lastFrame = 4): string
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
