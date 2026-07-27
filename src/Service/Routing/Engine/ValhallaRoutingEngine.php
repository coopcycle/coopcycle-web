<?php

namespace AppBundle\Service\Routing\Engine;

use AppBundle\Entity\Base\GeoCoordinates;

/**
 * Valhalla-backed routing engine.
 *
 * Talks to Valhalla in its OSRM-compatibility mode by setting
 * `format: "osrm"` and `shape_format: "polyline5"`. In that mode the
 * upstream response is byte-for-byte OSRM-shaped, so the engine simply
 * passes the payload through to `RoutingInterface` callers — distances
 * are already in metres, durations in seconds, polylines are precision 5
 * (Google's standard).
 *
 * Hard-coded to `costing=bicycle` to mirror the OSRM `bicycle.lua`
 * profile the rest of the platform assumes.
 */
class ValhallaRoutingEngine extends AbstractRoutingEngine
{
    private string $costing;

    public function __construct(\Symfony\Contracts\HttpClient\HttpClientInterface $valhallaClient, string $costing = 'bicycle')
    {
        parent::__construct($valhallaClient);
        $this->costing = $costing;
    }

    /**
     * Build the OSRM-compatible request body. `format=osrm` enables the
     * upstream response shim; `shape_format=polyline5` selects Google's
     * precision-5 polyline encoding.
     */
    private function baseBody(): array
    {
        return [
            'format' => 'osrm',
            'shape_format' => 'polyline5',
            'costing' => $this->costing,
            'id_match' => true,
        ];
    }

    /**
     * Build the `locations` array from coordinates.
     */
    private function locations(GeoCoordinates ...$coordinates): array
    {
        $out = [];
        foreach ($coordinates as $coordinate) {
            $out[] = [
                'lat' => $coordinate->getLatitude(),
                'lon' => $coordinate->getLongitude(),
            ];
        }

        return $out;
    }

    /**
     * Dispatch a Valhalla request and return the JSON-decoded body.
     *
     * @return array
     */
    private function call(string $endpoint, array $body, array $coordinates, array $options = [])
    {
        $cacheKey = $this->cacheKey($endpoint, $coordinates, $options);
        $uri = sprintf('%s?json=%s', $endpoint, urlencode(json_encode($body)));

        return $this->requestJson($cacheKey, 'GET', $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function getPolyline(GeoCoordinates ...$coordinates)
    {
        $body = $this->baseBody();
        $body['locations'] = $this->locations(...$coordinates);

        $response = $this->call('route', $body, $coordinates);

        return $response['routes'][0]['geometry'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates ...$coordinates)
    {
        $body = $this->baseBody();
        $body['locations'] = $this->locations(...$coordinates);

        $response = $this->call('route', $body, $coordinates);

        return (int) round((float) ($response['routes'][0]['distance'] ?? 0));
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates ...$coordinates)
    {
        $body = $this->baseBody();
        $body['locations'] = $this->locations(...$coordinates);

        $response = $this->call('route', $body, $coordinates);

        return (int) round((float) ($response['routes'][0]['duration'] ?? 0));
    }

    /**
     * {@inheritdoc}
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$destinations)
    {
        $body = $this->baseBody();
        $body['sources'] = $this->locations($source);
        $body['targets'] = $this->locations(...$destinations);

        $response = $this->call(
            'sources_to_targets',
            $body,
            array_merge([$source], $destinations),
        );

        // OSRM-shaped `distances` is a 2D matrix indexed by source then
        // destination. We only ever query one source, so pluck the row.
        $row = $response['distances'][0] ?? [];

        $distances = [];
        foreach ($row as $value) {
            $distances[] = (int) round((float) $value);
        }

        return $distances;
    }

    /**
     * {@inheritdoc}
     */
    public function route(GeoCoordinates ...$coordinates)
    {
        $body = $this->baseBody();
        $body['locations'] = $this->locations(...$coordinates);

        $response = $this->call('route', $body, $coordinates);

        // Pass through the OSRM-shaped payload directly. We only need to
        // make sure the top-level `code` is present; Valhalla already
        // returns "Ok" on success.
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function trip(GeoCoordinates ...$coordinates)
    {
        $body = $this->baseBody();
        $body['locations'] = $this->locations(...$coordinates);

        $response = $this->call('optimized_route', $body, $coordinates);

        return $response;
    }
}
