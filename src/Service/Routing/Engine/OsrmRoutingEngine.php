<?php

namespace AppBundle\Service\Routing\Engine;

use AppBundle\Entity\Base\GeoCoordinates;

/**
 * OSRM-backed routing engine.
 *
 * URL scheme:
 *   http://osrm:5000/route/v1/{profile}/{coordinates}?
 *   - alternatives={true|false}
 *   - steps={true|false}
 *   - geometries={polyline|polyline6|geojson}
 *   - overview={full|simplified|false}
 *   - annotations={true|false}
 */
class OsrmRoutingEngine extends AbstractRoutingEngine
{
    private string $profile;

    public function __construct(\Symfony\Contracts\HttpClient\HttpClientInterface $osrmClient, string $profile = 'bicycle')
    {
        parent::__construct($osrmClient);
        $this->profile = $profile;
    }

    /**
     * Generic OSRM call. Kept public for backwards compatibility with the
     * existing `Osrm::getServiceResponse($service, ...)` signature.
     *
     * @return array
     */
    public function getServiceResponse(string $service, array $coordinates, array $options = [])
    {
        $cacheKey = $this->cacheKey($service, $coordinates, $options);

        $coords = array_map(function (GeoCoordinates $coordinate) {
            return implode(',', [$coordinate->getLongitude(), $coordinate->getLatitude()]);
        }, $coordinates);
        $coordsAsString = implode(';', $coords);
        $queryString = http_build_query($options);

        $uri = sprintf('/%s/v1/%s/%s', $service, $this->profile, $coordsAsString);
        if (!empty($queryString)) {
            $uri .= '?' . $queryString;
        }

        return $this->requestJson($cacheKey, 'GET', $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function getPolyline(GeoCoordinates ...$coordinates)
    {
        $response = $this->getServiceResponse('route', $coordinates, ['overview' => 'full']);

        return $response['routes'][0]['geometry'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates ...$coordinates)
    {
        $response = $this->getServiceResponse('route', $coordinates, ['overview' => 'full']);

        return (int) $response['routes'][0]['distance'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates ...$coordinates)
    {
        $response = $this->getServiceResponse('route', $coordinates, ['overview' => 'full']);

        return (int) $response['routes'][0]['duration'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$destinations)
    {
        $coords = array_merge([$source], $destinations);

        $options = [
            'sources' => '0',
            'destinations' => implode(';', range(1, count($destinations))),
            'annotations' => 'distance',
        ];

        $response = $this->getServiceResponse('table', $coords, $options);

        return current($response['distances']);
    }

    /**
     * {@inheritdoc}
     */
    public function route(GeoCoordinates ...$coordinates)
    {
        return $this->getServiceResponse('route', $coordinates, [
            'steps' => 'true',
            'overview' => 'full',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function trip(GeoCoordinates ...$coordinates)
    {
        return $this->getServiceResponse('trip', $coordinates, [
            'steps' => 'true',
            'overview' => 'full',
        ]);
    }
}
