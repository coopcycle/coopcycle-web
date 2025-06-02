<?php

namespace AppBundle\Log;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestUserAgentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
    )
    {
    }

    public function __invoke(LogRecord $record): array
    {

        $request = $this->requestStack->getCurrentRequest();

        // for logs coming from ApiLogSubscriber the $request variable is null and `extra`s are added by the ApiRequestResponseProcessor

        if ($request && $request->headers->has('User-Agent')) {
            $record['extra']['user_agent'] = $request->headers->get('User-Agent');
        }

        return $record;
    }
}
