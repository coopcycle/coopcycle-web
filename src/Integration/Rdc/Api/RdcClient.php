<?php

namespace AppBundle\Integration\Rdc\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\ClientException;

class RdcClient implements RdcClientInterface
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    private ?string $traceparent = null;
    private ?string $cachedToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
        private readonly string $keycloakClientId,
        private readonly string $keycloakClientSecret,
        private readonly string $keycloakUsername,
        private readonly string $keycloakPassword,
        private readonly string $rdcInstanceBaseUrl,
        private readonly string $memberProvider,
        private readonly array $aclAuthorizations = [],
    ) {
    }

    public function get(string $path, array $query = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $data, array $headers = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('POST', $path, [], $data, $headers);
    }

    public function patch(string $path, array $patchOperations, array $headers = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $headers['Content-Type'] = 'application/json-patch+json';
        return $this->request('PATCH', $path, [], $patchOperations, $headers);
    }

    public function createEvent(string $path, array $eventData): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('POST', $path, [], $eventData);
    }

    public function getTraceparent(): string
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

    public function getToken(): string
    {
        return $this->getValidToken();
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $additionalHeaders = [],
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                return $this->doRequest($method, $path, $query, $body, $additionalHeaders);
            } catch (ClientException $e) {
                throw $e;
            } catch (ServerException $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt < self::MAX_RETRIES) {
                    $this->waitAndRetry($attempt);
                }
            } catch (TransportExceptionInterface $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt < self::MAX_RETRIES) {
                    $this->waitAndRetry($attempt);
                }
            }
        }

        throw new RdcException(
            'RDC API request failed after ' . self::MAX_RETRIES . ' retries: ' . ($lastException?->getMessage() ?? 'Unknown error'),
            previous: $lastException
        );
    }

    private function doRequest(
        string $method,
        string $path,
        array $query,
        array|null $body,
        array $additionalHeaders,
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        $token = $this->getValidToken();

        $headers = array_merge([
            'Authorization' => 'Bearer ' . $token,
            'X-BOL-Member-Identifier' => 'BOL.MEMBER.' . $this->memberProvider,
            'X-BOL-ACL-authorizations' => json_encode($this->aclAuthorizations),
            'traceparent' => $this->getTraceparent(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $additionalHeaders);

        $url = rtrim($this->rdcInstanceBaseUrl, '/') . '/' . ltrim($path, '/');

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

    private function getValidToken(): string
    {
        $bufferSeconds = 60;

        if ($this->cachedToken !== null && $this->tokenExpiresAt > time() + $bufferSeconds) {
            return $this->cachedToken;
        }

        return $this->refreshToken();
    }

    private function refreshToken(): string
    {
        $this->logger->info('Refreshing RDC access token');

        $url = rtrim($this->keycloakBaseUrl, '/') . '/realms/' . $this->keycloakRealm . '/protocol/openid-connect/token';

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'body' => [
                        'grant_type' => 'password',
                        'client_id' => $this->keycloakClientId,
                        'client_secret' => $this->keycloakClientSecret,
                        'username' => $this->keycloakUsername,
                        'password' => $this->keycloakPassword,
                    ],
                ]);

                $data = $response->toArray();

                $this->cachedToken = $data['access_token'];
                $this->tokenExpiresAt = time() + (int) $data['expires_in'];

                $this->logger->info('Token refresh successful', [
                    'expires_in' => $data['expires_in'],
                    'buffer_seconds' => 60,
                ]);

                return $this->cachedToken;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                $this->logger->warning('Token request failed, retrying', [
                    'attempt' => $attempt,
                    'max_retries' => self::MAX_RETRIES,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    $this->waitAndRetry($attempt);
                }
            }
        }

        throw new RdcException(
            'Failed to obtain token after ' . self::MAX_RETRIES . ' retries',
            previous: $lastException
        );
    }

    private function waitAndRetry(int $attempt): void
    {
        $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1));
        usleep($delay * 1000);
    }
}
