<?php

namespace AppBundle\Log;

use Monolog\Processor\ProcessorInterface;

class RequestResponseProcessor
{
    public function __invoke(array $record)
    {
        if (!isset($record['context']['request'], $record['context']['response'])) {
            return;
        }

        $request = $record['context']['request'];
        $response = $record['context']['response'];

        $requestHeaders = clone $request->headers;
        if ($requestHeaders->has('Authorization')) {
            if (preg_match('/^Bearer (.*)$/', $requestHeaders->get('Authorization'))) {
                // Obfuscate token
                $authorization = preg_replace('/^Bearer (.{4})(.*)(.{4})$/', 'Bearer ${1}✱✱✱✱${1}', $requestHeaders->get('Authorization'));
                $requestHeaders->set('Authorization', $authorization);
            }
        }

        $record['extra']['method'] = $request->getMethod();
        $record['extra']['request_uri'] = $request->getRequestUri();
        $record['extra']['status_code'] = $response->getStatusCode();
        $record['extra']['request_headers'] = (string) $requestHeaders;
        $record['extra']['request_body'] = (string) $request->getContent();
        $record['extra']['response_headers'] = (string) $response->headers;
        $record['extra']['response_body'] = (string) $response->getContent();

        unset($record['context']['request']);
        unset($record['context']['response']);

        return $record;
    }
}
