<?php

namespace Tests\AppBundle\Geocoder\Provider;

use AppBundle\Geocoder\Provider\AddokWithProximityProvider;
use Geocoder\Query\GeocodeQuery;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Verifies that the addok REST URL built by AddokWithProximityProvider
 * honours the `proximity` data key and the locale set on the GeocodeQuery.
 */
class AddokWithProximityProviderTest extends TestCase
{
    private const FEATURES_JSON = <<<'JSON'
{
    "features": [
        {
            "geometry": { "coordinates": [2.385274, 48.867432] },
            "properties": {
                "type": "housenumber",
                "street": "Rue des Panoyaux",
                "housenumber": "11",
                "city": "Paris",
                "postcode": "75020"
            }
        }
    ]
}
JSON;

    /**
     * Builds a PSR-18 client that records every outgoing request's URI and
     * replies with the canned response. Returns both the client and a
     * by-reference string that will hold the last URL.
     *
     * @return array{client: ClientInterface, lastUri: string}
     */
    private function capturingClient(string $body = self::FEATURES_JSON): array
    {
        $state = new class($body) {
            public string $lastUri = '';
            public string $body;

            public function __construct(string $body)
            {
                $this->body = $body;
            }
        };

        $client = new class($state) implements ClientInterface {
            private object $state;

            public function __construct(object $state)
            {
                $this->state = $state;
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->state->lastUri = (string) $request->getUri();

                return new Response(200, [], $this->state->body);
            }
        };

        return ['client' => $client, 'lastUri' => &$state->lastUri];
    }

    private function provider(ClientInterface $client): AddokWithProximityProvider
    {
        return new AddokWithProximityProvider($client, 'https://example.test/geocodage');
    }

    public function testForwardProximityWhenPresent(): void
    {
        $ctx = $this->capturingClient();

        $query = GeocodeQuery::create('11 Rue des Panoyaux Paris')
            ->withData('proximity', '48.856613,2.352222');

        $this->provider($ctx['client'])->geocodeQuery($query);

        $this->assertStringContainsString('lat=48.856613', $ctx['lastUri']);
        $this->assertStringContainsString('lon=2.352222', $ctx['lastUri']);
    }

    public function testProximityWithSurroundingWhitespaceIsTolerated(): void
    {
        $ctx = $this->capturingClient();

        $query = GeocodeQuery::create('11 Rue des Panoyaux Paris')
            ->withData('proximity', ' 48.856613 , 2.352222 ');

        $this->provider($ctx['client'])->geocodeQuery($query);

        $this->assertStringContainsString('lat=48.856613', $ctx['lastUri']);
        $this->assertStringContainsString('lon=2.352222', $ctx['lastUri']);
    }

    public function testOmitsProximityWhenAbsent(): void
    {
        $ctx = $this->capturingClient();

        $this->provider($ctx['client'])->geocodeQuery(GeocodeQuery::create('11 Rue des Panoyaux Paris'));

        $this->assertStringNotContainsString('lat=', $ctx['lastUri']);
        $this->assertStringNotContainsString('lon=', $ctx['lastUri']);
    }

    public function testMalformedProximityIsIgnored(): void
    {
        $ctx = $this->capturingClient();

        $query = GeocodeQuery::create('11 Rue des Panoyaux Paris')
            ->withData('proximity', 'not-a-pair');

        $this->provider($ctx['client'])->geocodeQuery($query);

        $this->assertStringNotContainsString('lat=', $ctx['lastUri']);
        $this->assertStringNotContainsString('lon=', $ctx['lastUri']);
    }

    public function testOutOfRangeCoordinatesAreIgnored(): void
    {
        $ctx = $this->capturingClient();

        $query = GeocodeQuery::create('11 Rue des Panoyaux Paris')
            ->withData('proximity', '999,999');

        $this->provider($ctx['client'])->geocodeQuery($query);

        $this->assertStringNotContainsString('lat=', $ctx['lastUri']);
        $this->assertStringNotContainsString('lon=', $ctx['lastUri']);
    }

    public function testLocaleIsForwardedAsLang(): void
    {
        $ctx = $this->capturingClient();

        $query = GeocodeQuery::create('11 Rue des Panoyaux Paris')
            ->withData('proximity', '48.856613,2.352222')
            ->withLocale('fr');

        $this->provider($ctx['client'])->geocodeQuery($query);

        $this->assertStringContainsString('lang=fr', $ctx['lastUri']);
    }

    public function testTypeDataIsPreserved(): void
    {
        $ctx = $this->capturingClient();

        $query = GeocodeQuery::create('11 Rue des Panoyaux Paris')
            ->withData('type', 'housenumber');

        $this->provider($ctx['client'])->geocodeQuery($query);

        $this->assertStringContainsString('type=housenumber', $ctx['lastUri']);
    }

    public function testSearchEndpointAndQueryAreBuilt(): void
    {
        $ctx = $this->capturingClient();

        $this->provider($ctx['client'])->geocodeQuery(GeocodeQuery::create('11 Rue des Panoyaux Paris'));

        $this->assertStringStartsWith('https://example.test/geocodage/search/', $ctx['lastUri']);
        $this->assertStringContainsString('q=11+Rue+des+Panoyaux+Paris', $ctx['lastUri']);
        $this->assertStringContainsString('autocomplete=0', $ctx['lastUri']);
    }

    public function testReturnsParsedAddress(): void
    {
        $ctx = $this->capturingClient();

        $results = $this->provider($ctx['client'])
            ->geocodeQuery(GeocodeQuery::create('11 Rue des Panoyaux Paris'));

        $this->assertCount(1, $results);
        $first = $results->first();
        $this->assertSame('addok', $first->getProvidedBy());
        $this->assertSame('Rue des Panoyaux', $first->getStreetName());
        $this->assertSame('11', $first->getStreetNumber());
        $this->assertSame('Paris', $first->getLocality());
        $this->assertSame('75020', $first->getPostalCode());
        $this->assertEqualsWithDelta(48.867432, $first->getCoordinates()->getLatitude(), 1e-6);
        $this->assertEqualsWithDelta(2.385274, $first->getCoordinates()->getLongitude(), 1e-6);
    }

    public function testEmptyFeaturesArrayYieldsEmptyCollection(): void
    {
        $ctx = $this->capturingClient('{"features": []}');

        $results = $this->provider($ctx['client'])->geocodeQuery(GeocodeQuery::create('nowhere'));

        $this->assertCount(0, $results);
    }
}
