<?php

namespace Tests\AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\Routing\Engine\ValhallaRoutingEngine;
use AppBundle\Service\Routing\Fallback;
use AppBundle\Service\Routing\Valhalla;
use AppBundle\Service\Routing\ValhallaWithFallback;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ValhallaWithFallbackTest extends TestCase
{
    private function withClient(MockHttpClient $client): ValhallaWithFallback
    {
        $engine = new ValhallaRoutingEngine($client, 'bicycle');

        return new ValhallaWithFallback(new Valhalla($engine), new Fallback());
    }

    /**
     * When Valhalla is unreachable the client throws a TransportException
     * (connection refused / DNS / timeout), which is the failure mode the
     * fallback exists for. Regression test: this used to leak past the
     * `catch` because only HttpExceptionInterface (4xx/5xx) was caught.
     */
    public function testFallsBackWhenServiceIsUnreachable()
    {
        $client = new MockHttpClient(function () {
            throw new TransportException('Connection refused');
        });
        $routing = $this->withClient($client);

        $a = new GeoCoordinates(48.85, 2.35);
        $b = new GeoCoordinates(48.86, 2.36);

        // Fallback computes a non-zero straight-line distance rather than
        // letting the transport error propagate.
        $this->assertGreaterThan(0, $routing->getDistance($a, $b));
    }

    /**
     * A 5xx from Valhalla is an HttpExceptionInterface and must also fall back.
     */
    public function testFallsBackOnServerError()
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $routing = $this->withClient($client);

        $a = new GeoCoordinates(48.85, 2.35);
        $b = new GeoCoordinates(48.86, 2.36);

        $this->assertGreaterThan(0, $routing->getDistance($a, $b));
    }

    public function testGetTripPassesThroughOnSuccess()
    {
        $body = json_encode([
            'code' => 'Ok',
            'trips' => [
                ['distance' => 1234.0, 'duration' => 987.0, 'geometry' => 'poly'],
            ],
        ]);
        $client = new MockHttpClient(new MockResponse($body));
        $routing = $this->withClient($client);

        $a = new GeoCoordinates(48.85, 2.35);
        $b = new GeoCoordinates(48.86, 2.36);

        $result = $routing->getTrip($a, $b);

        $this->assertEquals('Ok', $result['code']);
        $this->assertArrayHasKey('trips', $result);
    }

    public function testGetTripFallsBackToRouteShapeOnFailure()
    {
        $client = new MockHttpClient(function () {
            throw new TransportException('Connection refused');
        });
        $routing = $this->withClient($client);

        $a = new GeoCoordinates(48.85, 2.35);
        $b = new GeoCoordinates(48.86, 2.36);

        // The fallback for getTrip returns the OSRM `route` shape.
        $result = $routing->getTrip($a, $b);

        $this->assertEquals('Ok', $result['code']);
        $this->assertArrayHasKey('routes', $result);
    }
}
