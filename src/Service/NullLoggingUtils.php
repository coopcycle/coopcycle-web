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

    public function getOrderId($order): string
    {
        return '#1234';
    }
}
