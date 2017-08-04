<?php

namespace AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;

/**
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
    private $osrmHost;

    public function __construct($osrmHost)
    {
        $this->osrmHost = $osrmHost;
    }

    public function getRawResponse(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $originCoords = implode(',', [ $origin->getLongitude(), $origin->getLatitude() ]);
        $destinationCoords = implode(',', [ $destination->getLongitude(), $destination->getLatitude() ]);

        $response = file_get_contents('http://' . $this->osrmHost. "/route/v1/bicycle/{$originCoords};{$destinationCoords}?overview=full");

        if ($response) {
            return json_decode($response, true);
        }
    }

    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $response = $this->getRawResponse($origin, $destination);

        return $response['routes'][0]['geometry'];
    }

    public function getDistance(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $response = $this->getRawResponse($origin, $destination);

        return $response['routes'][0]['distance'];
    }
}
