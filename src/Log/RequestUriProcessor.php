<?php

namespace AppBundle\Log;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestUriProcessor implements ProcessorInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(LogRecord $record): LogRecord
    {

        $request = $this->requestStack->getCurrentRequest();

        // for logs coming from ApiLogSubscriber the $request variable is null and `extra`s are added by the ApiRequestResponseProcessor

        if ($request) {
            $record['extra']['method'] = $request->getMethod();
            $record['extra']['request_uri'] = $request->getRequestUri();
        }

        return $record;
    }
}
