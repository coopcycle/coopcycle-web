<?php

namespace AppBundle\Geography\CityZoneImporter;

use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;
use AppBundle\Entity\CityZone;
use AppBundle\Geography\CityZoneImporterInterface;
use GeoJson\Geometry\Polygon;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EsriJson implements CityZoneImporterInterface
{
	public function __construct(private HttpClientInterface $client, private Proj4php $proj4)
	{}

	public function import(string $url, array $options = []): array
	{
		$response = $this->client->request('GET', $url);

        $jsonData = $response->toArray();

        $fromProj = new Proj(sprintf('EPSG:%s', $jsonData['spatialReference']['wkid']), $this->proj4);
        $toProj = new Proj('EPSG:4326', $this->proj4);

        $cityZones = [];

        foreach ($jsonData['features'] as $feature) {

            $rings = [];

            foreach ($feature['geometry']['rings'] as $ring) {

                $coords = array_map(function ($coord) use ($fromProj, $toProj) {
                    $pointSrc = new Point($coord[0], $coord[1], $fromProj);
                    $pointDest = $this->proj4->transform($toProj, $pointSrc);

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

