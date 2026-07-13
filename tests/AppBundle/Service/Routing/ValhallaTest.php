<?php

namespace Tests\AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\Routing\Engine\ValhallaRoutingEngine;
use AppBundle\Service\Routing\Valhalla;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ValhallaTest extends TestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    private $client;
    private $valhalla;

    public function setUp(): void
    {
        $this->client = new MockHttpClient(null, 'http://valhalla');
        $engine = new ValhallaRoutingEngine($this->client, 'bicycle');
        $this->valhalla = new Valhalla($engine);
    }

    public function testRequestIsCached()
    {
        // OSRM-shaped payload that Valhalla returns when `format=osrm`
        // and `shape_format=polyline5` are set.
        $responseBody = [
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 3000,
                    'duration' => 3600,
                    'geometry' => 'abcdefgh',
                ],
            ],
            'waypoints' => [],
        ];

        $mockResponse = new MockResponse(json_encode($responseBody));
        $this->client->setResponseFactory([$mockResponse]);

        $coord1 = new GeoCoordinates(48.856613, 2.352222);
        $coord2 = new GeoCoordinates(48.856613, 2.352222);
        $coord3 = new GeoCoordinates(48.856613, 2.352222);

        $this->assertEquals(3000, $this->valhalla->getDistance($coord1, $coord2, $coord3));
        $this->assertEquals(3000, $this->valhalla->getDistance(...[$coord1, $coord2, $coord3]));
        $this->assertEquals(3600, $this->valhalla->getDuration($coord1, $coord2, $coord3));
        $this->assertEquals('abcdefgh', $this->valhalla->getPolyline($coord1, $coord2, $coord3));

        $requestUrl = $mockResponse->getRequestUrl();
        $this->assertStringStartsWith('http://valhalla/route?', $requestUrl);

        $queryString = parse_url($requestUrl, PHP_URL_QUERY) ?? '';
        $parsed = [];
        parse_str($queryString, $parsed);
        $this->assertArrayHasKey('json', $parsed);
        $decoded = json_decode($parsed['json'], true);
        $this->assertEquals('osrm', $decoded['format']);
        $this->assertEquals('polyline5', $decoded['shape_format']);
        $this->assertEquals('bicycle', $decoded['costing']);
        $this->assertTrue($decoded['id_match']);
        $this->assertCount(3, $decoded['locations']);
    }

    public function testRouteReturnsOsrmShape()
    {
        $responseBody = [
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 1234.0,
                    'duration' => 987.0,
                    'geometry' => 'polylineXYZ',
                    'legs' => [],
                ],
            ],
            'waypoints' => [
                ['hint' => '', 'name' => 'A', 'location' => [2.35, 48.85]],
                ['hint' => '', 'name' => 'B', 'location' => [2.36, 48.86]],
            ],
        ];
        $mockResponse = new MockResponse(json_encode($responseBody));
        $this->client->setResponseFactory([$mockResponse]);

        $coord1 = new GeoCoordinates(48.85, 2.35);
        $coord2 = new GeoCoordinates(48.86, 2.36);

        $result = $this->valhalla->route($coord1, $coord2);

        $this->assertEquals('Ok', $result['code']);
        $this->assertCount(1, $result['routes']);
        $this->assertEquals('polylineXYZ', $result['routes'][0]['geometry']);
        $this->assertEquals(1234, $result['routes'][0]['distance']);
        $this->assertEquals(987, $result['routes'][0]['duration']);
        $this->assertCount(2, $result['waypoints']);
        $this->assertEquals('A', $result['waypoints'][0]['name']);
    }

    public function testGetDistancesReturnsFlatList()
    {
        // Valhalla's OSRM-shaped `distances` is already in meters.
        $responseBody = [
            'code' => 'Ok',
            'distances' => [[1100.0, 2200.0]],
            'durations' => [[600, 1200]],
        ];
        $mockResponse = new MockResponse(json_encode($responseBody));
        $this->client->setResponseFactory([$mockResponse]);

        $source = new GeoCoordinates(48.85, 2.35);
        $a = new GeoCoordinates(48.86, 2.36);
        $b = new GeoCoordinates(48.87, 2.37);

        $distances = $this->valhalla->getDistances($source, $a, $b);

        $this->assertEquals([1100, 2200], $distances);
        $this->assertStringContainsString('sources_to_targets', $mockResponse->getRequestUrl());
    }
}
