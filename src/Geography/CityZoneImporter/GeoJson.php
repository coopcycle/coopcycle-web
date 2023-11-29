<?php

namespace AppBundle\Geography\CityZoneImporter;

use AppBundle\Entity\CityZone;
use AppBundle\Geography\CityZoneImporterInterface;
use GeoJson\Feature\FeatureCollection;
use GeoJson\GeoJson as GeoJsonLib;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoJson implements CityZoneImporterInterface
{
    public function __construct(private HttpClientInterface $client)
    {}

    public function import(string $url, array $options = []): array
    {
        $cityZones = [];

        $response = $this->client->request('GET', $url);

        $jsonData = $response->toArray();

        $geoJson = GeoJsonLib::jsonUnserialize($jsonData);

        if ($geoJson instanceof FeatureCollection) {
            foreach ($geoJson->getFeatures() as $feature) {
                $properties = $feature->getProperties();

                $geometry = $feature->getGeometry();
                if (null !== $geometry) {
                    $cityZone = new CityZone();
                    $cityZone->setGeoJSON($feature->getGeometry());
                    $cityZones[] = $cityZone;
                }
            }
        }

        return $cityZones;
    }
}

