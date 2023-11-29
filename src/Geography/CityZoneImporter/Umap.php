<?php

namespace AppBundle\Geography\CityZoneImporter;

use AppBundle\Entity\CityZone;
use AppBundle\Geography\CityZoneImporterInterface;
use GeoJson\GeoJson;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Umap implements CityZoneImporterInterface
{
	public function __construct(private HttpClientInterface $umapClient)
	{}

	public function import(string $url, array $options = []): array
	{
		// https://github.com/umap-project/umap/issues/78
        preg_match('#umap.openstreetmap.fr/[a-z]{2}/map/[a-z0-9\-]+_([0-9]+)#', $url, $matches);

        $mapId = $matches[1];

        $response = $this->umapClient->request('GET', sprintf('fr/map/%s/geojson/', $mapId));

        $umapData = $response->toArray();

        $dataLayerViewUrl = $umapData['properties']['urls']['datalayer_view'];

        // TODO Find which layer to use

        $datalayer = $umapData['properties']['datalayers'][0];

        $url = str_replace('{map_id}', $mapId, $dataLayerViewUrl);
        $url = str_replace('{pk}', $datalayer['id'], $url);

        $response = $this->umapClient->request('GET', ltrim($url, '/'));

        $jsonData = $response->toArray();

        $cityZones = [];

        /** @var \GeoJson\Feature\FeatureCollection */
        $featureCollection = GeoJson::jsonUnserialize($jsonData);
        foreach ($featureCollection->getFeatures() as $feature) {

            $properties = $feature->getProperties();

            $cityZone = new CityZone();
            $cityZone->setName($properties['name'] ?? '');
            $cityZone->setGeoJSON($feature->getGeometry());

            $cityZones[] = $cityZone;
        }

		return $cityZones;
	}
}
