<?php

namespace AppBundle\Service\Routing\Engine;

use AppBundle\Entity\Base\GeoCoordinates;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractRoutingEngine implements RoutingEngineInterface
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var array<string, array>
     */
    protected $cache = [];

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Build a unique cache key for a backend call.
     */
    protected function cacheKey(string $endpoint, array $coordinates, array $options = []): string
    {
        $coords = array_map(function (GeoCoordinates $coordinate) {
            return implode(',', [$coordinate->getLongitude(), $coordinate->getLatitude()]);
        }, $coordinates);

        $coordsAsString = implode(';', $coords);
        $queryString = http_build_query($options);

        $key = sprintf('%s://%s', $endpoint, $coordsAsString);
        if (!empty($queryString)) {
            $key .= '?' . $queryString;
        }

        return $key;
    }

    /**
     * Run a GET request, decode the JSON response, and cache the result.
     *
     * @return array
     */
    protected function requestJson(string $cacheKey, string $method, string $uri, array $options = [])
    {
        if (!isset($this->cache[$cacheKey])) {
            $response = $this->client->request($method, $uri, $options);
            $this->cache[$cacheKey] = json_decode($response->getContent(), true);
        }

        return $this->cache[$cacheKey];
    }
}
