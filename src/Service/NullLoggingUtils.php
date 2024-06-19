<?php

namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class NullLoggingUtils extends LoggingUtils
{
    public function __construct()
    {
        parent::__construct(new RequestStack());
    }

    public function getBacktrace(int $firstFrame = 1, int $lastFrame = 3): string
    {
        return 'function: ? | file: ? | line: ?';
    }

    public function getRequest(): string
    {
        return 'GET /';
    }

    public function redact(string $text, int $symbolsAtStart = 4, int $symbolsAtEnd = 4, int $symbolsInMiddle = 4): string
    {
        return $text;
    }

    public function getOrderId($order): string
    {
        return '#1234';
    }

    public function getVendors($order): string
    {
        return '1, 2, 3';
    }
}
