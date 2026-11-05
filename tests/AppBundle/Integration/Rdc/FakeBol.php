<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Answers for the BOL nodes and their Keycloak, as the response factory of the
 * test environment's `rdc_client_mock`, and records the calls the RDC code makes.
 *
 * The status codes are the ones the BOL returns in production: 200 for the
 * creations, the patches and the change-requests, 201 for the events.
 */
final class FakeBol
{
    public const REVISION = '7';

    /** @var array<int, array{method: string, url: string, path: string, query: array, headers: array, body: ?array}> */
    private array $calls = [];

    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $body = $options['body'] ?? null;
        $decoded = is_string($body) ? json_decode($body, true) : null;

        if (str_ends_with((string) parse_url($url, PHP_URL_PATH), '/protocol/openid-connect/token')) {
            return new MockResponse((string) json_encode([
                'access_token' => 'bol-token',
                'expires_in' => 300,
                'refresh_token' => 'bol-refresh-token',
                'refresh_expires_in' => 1800,
                'token_type' => 'Bearer',
            ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
        }

        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'path' => (string) parse_url($url, PHP_URL_PATH),
            'query' => $query,
            'headers' => $options['normalized_headers'] ?? [],
            'body' => is_array($decoded) ? $decoded : null,
        ];

        // Reading a service request on the requestor's node gives its revision
        if ('GET' === $method) {
            return new MockResponse('{}', [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json', 'x-revision' => self::REVISION],
            ]);
        }

        // Production refused every service linked to a single activity, instead
        // of a list of them
        if ('PATCH' === $method && is_array($decoded) && isset($decoded['scheduledActivities'])
            && !array_is_list($decoded['scheduledActivities'])) {
            return new MockResponse('{"message":"Bad Request"}', [
                'http_code' => 400,
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        }

        // Creating a service or an activity returns it, with the id it was given
        if ('POST' === $method && isset($query['id'])) {
            return new MockResponse((string) json_encode(['id' => $query['id']] + (is_array($decoded) ? $decoded : [])), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        }

        return new MockResponse('{}', [
            'http_code' => str_ends_with((string) parse_url($url, PHP_URL_PATH), '/events') ? 201 : 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * The calls as "METHOD path", to check their order at a glance.
     *
     * @return string[]
     */
    public function summary(): array
    {
        return array_map(fn (array $call) => sprintf('%s %s', $call['method'], $call['path']), $this->calls);
    }

    public function find(string $method, string $path): array
    {
        $matching = array_values(array_filter(
            $this->calls,
            fn (array $call) => $call['method'] === $method && $call['path'] === $path
        ));

        return $matching;
    }

    public function forget(): void
    {
        $this->calls = [];
    }
}
