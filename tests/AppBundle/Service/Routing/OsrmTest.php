<?php

namespace Tests\AppBundle\Service\Routing;

use AppBundle\Service\Routing\Osrm;
use AppBundle\Entity\Base\GeoCoordinates;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OsrmTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->client = new MockHttpClient(null, 'http://osrm');
        $this->osrm = new Osrm($this->client);
    }

    public function testRequestIsCached()
    {
        $responseBody = [
            'routes' => [
                [
                    'distance' => 3000,
                    'duration' => 3600,
                    'geometry' => 'abcdefgh',
                ]
            ]
        ];

        $mockResponse = new MockResponse(json_encode($responseBody));

        $responses = [
            $mockResponse
        ];

        $this->client->setResponseFactory($responses);

        $coord1 = new GeoCoordinates(48.856613, 2.352222);
        $coord2 = new GeoCoordinates(48.856613, 2.352222);
        $coord3 = new GeoCoordinates(48.856613, 2.352222);

        $this->assertEquals(3000, $this->osrm->getDistance($coord1, $coord2, $coord3));
        $this->assertEquals(3000, $this->osrm->getDistance(...[$coord1, $coord2, $coord3]));
        $this->assertEquals(3600, $this->osrm->getDuration($coord1, $coord2, $coord3));
        $this->assertEquals('abcdefgh', $this->osrm->getPolyline($coord1, $coord2, $coord3));

        $this->assertEquals('http://osrm/route/v1/bicycle/2.352222,48.856613;2.352222,48.856613;2.352222,48.856613?overview=full', $mockResponse->getRequestUrl());
    }
}
