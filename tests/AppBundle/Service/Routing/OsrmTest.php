<?php

namespace Tests\AppBundle\Service\Routing;

use AppBundle\Service\Routing\Osrm;
use AppBundle\Entity\Base\GeoCoordinates;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class OsrmTest extends TestCase
{
    public function setUp(): void
    {
        $this->client = $this->prophesize(Client::class);

        $this->osrm = new Osrm($this->client->reveal());
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

        $this->client
            ->request('GET', '/route/v1/bicycle/2.352222,48.856613;2.352222,48.856613?overview=full')
            ->willReturn(new Response(200, [], json_encode($responseBody)))
            ->shouldBeCalledTimes(1);

        $origin = new GeoCoordinates(48.856613, 2.352222);
        $destination = new GeoCoordinates(48.856613, 2.352222);

        $this->assertEquals(3000, $this->osrm->getDistance($origin, $destination));
        $this->assertEquals(3000, $this->osrm->getDistance(...[$origin, $destination]));
        $this->assertEquals(3600, $this->osrm->getDuration($origin, $destination));
        $this->assertEquals('abcdefgh', $this->osrm->getPolyline($origin, $destination));
    }
}
