<?php

namespace AppBundle\Log;

use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestClientIpProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
    )
    {
    }

    public function __invoke(array $record): array
    {

        $request = $this->requestStack->getCurrentRequest();

        // for logs coming from ApiLogSubscriber the $request variable is null and `extra`s are added by the ApiRequestResponseProcessor

        if ($request && $request->headers->has('X-Forwarded-For')) {
            $record['extra']['client_ip'] = $request->headers->get('X-Forwarded-For');
        }

        return $record;
    }
}
