<?php

namespace AppBundle\Integration\Rdc\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

class RdcClient implements RdcClientInterface
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    private ?string $traceparent = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly RdcClientConfig $config,
        private readonly TokenManagerInterface $tokenManager,
    ) {
    }

    public function get(string $path, array $query = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $data, array $query = [], array $headers = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('POST', $path, $query, $data, $headers);
    }

    public function patch(string $path, array $data, array $headers = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('PATCH', $path, [], $data, $headers);
    }

    public function postRemote(string $url, array $data, ?string $tokenOverride = null): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->requestRemote('POST', $url, $data, $tokenOverride);
    }

    public function getBaseUrl(): string
    {
        return $this->config->getApiBaseUrl();
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $additionalHeaders = [],
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        return $this->executeWithRetry(
            fn () => $this->doRequest($method, $path, $query, $body, $additionalHeaders),
            [ClientException::class, \Symfony\Component\HttpClient\Exception\ServerException::class],
        );
    }

    private function requestRemote(
        string $method,
        string $url,
        array $body,
        ?string $tokenOverride = null,
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        return $this->executeWithRetry(
            fn () => $this->doRequestRemote($method, $url, $body, $tokenOverride),
            [ClientException::class, \Symfony\Component\HttpClient\Exception\ServerException::class],
        );
    }

    private function doRequest(
        string $method,
        string $path,
        array $query,
        ?array $body,
        array $additionalHeaders,
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        $token = $this->tokenManager->getValidToken();

        $headers = [
            'Authorization' => sprintf('Bearer %s', $token),
            'X-BOL-Member-Identifier' => sprintf('BOL.MEMBER.%s', $this->config->memberProvider),
            'traceparent' => $this->getTraceparent(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            ...$additionalHeaders,
        ];

        if (!empty($this->config->aclAuthorizations)) {
            $headers['X-BOL-ACL-authorizations'] = json_encode($this->config->aclAuthorizations);
        }

        $url = $this->config->getApiBaseUrl() . '/' . ltrim($path, '/');

        $options = ['headers' => $headers];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        $this->logger->debug('RDC API request', [
            'method' => $method,
            'url' => $url,
            'has_body' => $body !== null,
        ]);

        $response = $this->httpClient->request($method, $url, $options);

        $this->logger->debug('RDC API response', [
            'status' => $response->getStatusCode(),
            'url' => $url,
        ]);

        return $response;
    }

    private function doRequestRemote(
        string $method,
        string $url,
        array $body,
        ?string $tokenOverride = null,
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        $headers = [
            'traceparent' => $this->getTraceparent(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Only add Authorization header if tokenOverride is not empty string
        // Empty string signals "no auth" for testing with mock servers
        if ($tokenOverride !== '') {
            $token = $tokenOverride ?? $this->tokenManager->getValidToken();
            $headers['Authorization'] = sprintf('Bearer %s', $token);
            $headers['X-BOL-Member-Identifier'] = sprintf('BOL.MEMBER.%s', $this->config->memberProvider);

            if (!empty($this->config->aclAuthorizations)) {
                $headers['X-BOL-ACL-authorizations'] = json_encode($this->config->aclAuthorizations);
            }
        }

        $options = [
            'headers' => $headers,
            'json' => $body,
        ];

        $this->logger->debug('RDC API remote request', [
            'method' => $method,
            'url' => $url,
            'has_body' => true,
            'headers' => $headers,
            'body' => $body,
        ]);

        $response = $this->httpClient->request($method, $url, $options);

        $this->logger->debug('RDC API remote response', [
            'status' => $response->getStatusCode(),
            'url' => $url,
        ]);

        return $response;
    }

    private function executeWithRetry(callable $operation, array $nonRetryableExceptions): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->logger->debug('RDC executeWithRetry attempt', ['attempt' => $attempt + 1]);
                return $operation();
            } catch (\Throwable $e) {
                $this->logger->warning('RDC request exception caught', [
                    'attempt' => $attempt + 1,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'is_retryable' => !in_array(get_class($e), $nonRetryableExceptions, true),
                ]);

                if (in_array(get_class($e), $nonRetryableExceptions, true)) {
                    throw $e;
                }

                $lastException = $e;
                $attempt++;

                if ($attempt < self::MAX_RETRIES) {
                    $this->waitAndRetry($attempt);
                }
            }
        }

        $this->logger->error('RDC request failed after all retries', [
            'max_retries' => self::MAX_RETRIES,
            'last_exception' => $lastException?->getMessage(),
        ]);

        throw RdcException::requestFailed(
            sprintf('failed after %d retries', self::MAX_RETRIES),
            $lastException
        );
    }

    private function waitAndRetry(int $attempt): void
    {
        $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1));
        usleep((int) (1000 * $delay));
    }

    private function getTraceparent(): string
    {
        if ($this->traceparent === null) {
            $version = '00';
            $traceId = bin2hex(random_bytes(16));
            $spanId = bin2hex(random_bytes(8));
            $flags = '01';
            $this->traceparent = implode('-', [$version, $traceId, $spanId, $flags]);
        }
        return $this->traceparent;
    }
}
