<?php

namespace AppBundle\Log;

use Monolog\Processor\ProcessorInterface;

class ApiRequestResponseProcessor
{
    public function __invoke(array $record)
    {
        if (!isset($record['context']['request'], $record['context']['response'])) {
            return;
        }

        $request = $record['context']['request'];
        $response = $record['context']['response'];

        $requestBody = (string) $request->getContent();

        if (preg_match('/refresh_token=/', $requestBody)) {
            $requestBody = preg_replace('/refresh_token=([^&]{4})([^&]+)([^&]{4})/', 'refresh_token=${1}✱✱✱✱${3}${4}', $requestBody);
        }

        $requestHeaders = clone $request->headers;
        if ($requestHeaders->has('Authorization')) {
            if (preg_match('/^Bearer (.*)$/', $requestHeaders->get('Authorization'))) {
                // Obfuscate token
                $authorization = preg_replace('/^Bearer (.{4})(.+)(.{4})$/', 'Bearer ${1}✱✱✱✱${3}', $requestHeaders->get('Authorization'));
                $requestHeaders->set('Authorization', $authorization);
            }
        }

        $responseBody = (string) $response->getContent();

        if ($response->headers->has('Content-Type')) {
            $contentType = $response->headers->get('Content-Type');
            if ($contentType === 'application/json') {
                $responseData = json_decode($responseBody, true);
                if (isset($responseData['token'])) {
                    $responseData['token'] = preg_replace('/^(.{4})(.+)(.{4})$/', '${1}✱✱✱✱${3}', $responseData['token']);
                    $responseBody = json_encode($responseData);
                }
                if (isset($responseData['refresh_token'])) {
                    $responseData['refresh_token'] = preg_replace('/^(.{4})(.+)(.{4})$/', '${1}✱✱✱✱${3}', $responseData['refresh_token']);
                    $responseBody = json_encode($responseData);
                }
            }
        }

        if ($request->headers->has('X-Request-ID')) {
            $record['extra']['request_id'] = $request->headers->get('X-Request-ID');
        }

        $record['extra']['method'] = $request->getMethod();
        $record['extra']['request_uri'] = $request->getRequestUri();
        $record['extra']['status_code'] = $response->getStatusCode();
        $record['extra']['request_headers'] = (string) $requestHeaders;
        $record['extra']['request_body'] = $requestBody;
        $record['extra']['response_headers'] = (string) $response->headers;
        $record['extra']['response_body'] = $responseBody;

        unset($record['context']['request']);
        unset($record['context']['response']);

        return $record;
    }
}
