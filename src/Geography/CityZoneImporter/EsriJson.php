<?php

namespace AppBundle\Geography\CityZoneImporter;

use AppBundle\Entity\CityZone;
use AppBundle\Geography\CityZoneImporterInterface;
use GeoJson\Geometry\Polygon;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EsriJson implements CityZoneImporterInterface
{
	public function __construct(private HttpClientInterface $client)
	{}

	public function import(string $url): array
	{
		$response = $this->client->request('GET', $url);

        $jsonData = $response->toArray();

        $proj4 = new \proj4php\Proj4php();
        $proj4->addDef('EPSG:25830','+proj=utm +zone=30 +ellps=GRS80 +units=m +no_defs');

        $fromProj = new \proj4php\Proj(sprintf('EPSG:%s', $jsonData['spatialReference']['wkid']), $proj4);
        $toProj = new \proj4php\Proj('EPSG:4326', $proj4);

        $cityZones = [];

        foreach ($jsonData['features'] as $feature) {

            $rings = [];

            foreach ($feature['geometry']['rings'] as $ring) {

                $coords = array_map(function ($coord) use ($proj4, $fromProj, $toProj) {
                    $pointSrc = new \proj4php\Point($coord[0], $coord[1], $fromProj);
                    $pointDest = $proj4->transform($toProj, $pointSrc);

                    return [ $pointDest->x, $pointDest->y ];
                }, $ring);

                $rings[] = $coords;

            }

            $polygon = new Polygon($rings);
            $cityZone = new CityZone();
            // $cityZone->setName($properties['name'] ?? '');
            $cityZone->setGeoJSON($polygon);

            $cityZones[] = $cityZone;
        }

        return $cityZones;
	}
}

