<?php

namespace AppBundle\Geography\CityZoneImporter;

use AppBundle\Entity\CityZone;
use AppBundle\Geography\CityZoneImporterInterface;
use GeoJson\Geometry\Polygon;
use function JmesPath\search;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Json implements CityZoneImporterInterface
{
    public function __construct(private HttpClientInterface $client)
    {}

    public function import(string $url, array $options = []): array
    {
        $cityZones = [];

        $response = $this->client->request('GET', $url);

        $jsonData = $response->toArray();

        $results = search($options['coordinates_path'], $jsonData);

        foreach ($results as $coordinates) {

        	$polygon = new Polygon([$coordinates]);

        	$cityZone = new CityZone();
            $cityZone->setGeoJSON($polygon);

            $cityZones[] = $cityZone;
        }

        return $cityZones;
    }
}


