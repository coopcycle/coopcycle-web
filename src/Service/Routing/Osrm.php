<?php

namespace AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * URL scheme:
 * http://osrm:5000/route/v1/{profile}/{coordinates}?
 * - alternatives={true|false}
 * - steps={true|false}
 * - geometries={polyline|polyline6|geojson}
 * - overview={full|simplified|false}
 * - annotations={true|false}
 *
 * Example API call:
 * http://router.project-osrm.org/route/v1/driving/13.388860,52.517037;13.397634,52.529407;13.428555,52.523219?overview=false
 *
 * Example JSON response:
 * {
 *   "code":"Ok",
 *   "routes":[
 *     {
 *       "geometry":"<polyline>",
 *       "legs":[
 *         {
 *           "steps" *   ],
 *           "distance":1351.7,
 *           "duration":338.1,
 *           "summary":"",
 *           "weight":338.1
 *         }
 *       ],
 *       "distance":1351.7,
 *       "duration":338.1,
 *       "weight_name":"duration",
 *       "weight":338.1
 *     }
 *   ],
 *   "waypoints":[
 *     {
 *       "hint":"<polyline>",
 *       "name":"Avenue Claude Vellefaux",
 *       "location":[
 *         2.370302,
 *         48.875046
 *       ]
 *     },
 *     {
 *       "hint":"<polyline>",
 *       "name":"Avenue de la République",
 *       "location":[
 *         2.376735,
 *         48.864846
 *       ]
 *     }
 *   ]
 * }
 */
class Osrm extends Base
{
    /**
     * @var HttpClientInterface
     */
    private $osrmClient;

    private $cache = [];

    /**
     * @param HttpClientInterface $osrmClient
     */
    public function __construct(HttpClientInterface $osrmClient)
    {
        $this->osrmClient = $osrmClient;
    }

    public function getServiceResponse($service, array $coordinates, array $options = [])
    {
        $coords = array_map(function($coordinate) {
            // String of format {longitude},{latitude};{longitude},{latitude}[;{longitude},{latitude} ...] or polyline({polyline}) or polyline6({polyline6}) .
            return implode(',', [ $coordinate->getLongitude(), $coordinate->getLatitude() ]);
        }, $coordinates);

        $coordsAsString = implode(';', $coords);
        $queryString = http_build_query($options);

        $cacheKey = sprintf('%s://%s', $service, $coordsAsString);
        if (!empty($queryString)) {
            $cacheKey .= '?'.$queryString;
        }

        if (!isset($this->cache[$cacheKey])) {
            $uri = "/{$service}/v1/bicycle/{$coordsAsString}" . (!empty($queryString) ? ('?'.$queryString) : '');
            $response = $this->osrmClient->request('GET', $uri);
            $data = json_decode($response->getContent(), true);

            $this->cache[$cacheKey] = $data;
        }

        return $this->cache[$cacheKey];
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
        $coords = array_merge([ $source ], $destinations);

        $options = [
            'sources' => '0',
            'destinations' => implode(';', range(1, count($destinations))),
            'annotations' => 'distance',
        ];

        $response = $this->getServiceResponse('table', $coords, $options);

        return current($response['distances']);
    }

    public function route(GeoCoordinates ...$coordinates)
    {
        return $this->getServiceResponse(
            'route',
            $coordinates,
            [
                'steps' => 'true',
                'overview' => 'full'
            ]
        );
    }
}
