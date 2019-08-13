<?php

namespace AppBundle\Monolog\Processor;

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

        $record['extra']['method'] = $request->getMethod();
        $record['extra']['request_uri'] = $request->getRequestUri();
        $record['extra']['status_code'] = $response->getStatusCode();

        $record['extra']['request_headers'] = (string) $request->headers;
        $record['extra']['request_body'] = (string) $request->getContent();

        $record['extra']['response_headers'] = (string) $response->headers;
        $record['extra']['response_body'] = (string) $response->getContent();

        unset($record['context']['request']);
        unset($record['context']['response']);

        return $record;
    }
}
