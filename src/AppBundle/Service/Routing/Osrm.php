<?php

namespace AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use GuzzleHttp\Client;

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
     * @var Client
     */
    private $client;

    private $cache = [];

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param GeoCoordinates $origin
     * @param GeoCoordinates $destination
     * @return array|null
     */
    public function getRawResponse(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        return $this->getServiceResponse('route', [ $origin, $destination ], ['overview' => 'full']);
    }

    public function getServiceResponse($service, array $coordinates, array $options = [])
    {
        $coords = array_map(function($coordinate) {
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
            $response = $this->client->request('GET', $uri);
            $data = json_decode($response->getBody(), true);

            $this->cache[$cacheKey] = $data;
        }

        return $this->cache[$cacheKey];
    }

    /**
     * @param GeoCoordinates $origin
     * @param GeoCoordinates $destination
     * @return mixed
     */
    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $response = $this->getRawResponse($origin, $destination);

        return $response['routes'][0]['geometry'];
    }

    /**
     * @param GeoCoordinates $origin
     * @param GeoCoordinates $destination
     * @return mixed
     */
    public function getDistance(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $response = $this->getRawResponse($origin, $destination);

        return $response['routes'][0]['distance'];
    }

    /**
     * @param GeoCoordinates $origin
     * @param GeoCoordinates $destination
     * @return mixed
     */
    public function getDuration(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $response = $this->getRawResponse($origin, $destination);

        return $response['routes'][0]['duration'];
    }
}
