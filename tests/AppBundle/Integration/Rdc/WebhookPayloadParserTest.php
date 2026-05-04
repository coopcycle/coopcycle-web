<?php

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\Webhook\WebhookPayloadParser;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class WebhookPayloadParserTest extends TestCase
{
    private WebhookPayloadParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WebhookPayloadParser(new NullLogger());
    }

    public function testParseValidPayload(): void
    {
        $payload = [
            [
                'metadata' => [
                    'loUri' => 'lo://rdc/service-request/123',
                    'eventType' => 'create',
                ],
                'lo' => [
                    'serviceRequestId' => 'SR-123',
                    'serviceType' => 'delivery',
                ],
            ]
        ];

        $result = $this->parser->parse($payload);

        $this->assertNotNull($result);
        $this->assertEquals('lo://rdc/service-request/123', $result['loUri']);
        $this->assertEquals('create', $result['eventType']);
        $this->assertEquals('SR-123', $result['lo']['serviceRequestId']);
    }

    public function testParseReturnsNullForEmptyPayload(): void
    {
        $this->assertNull($this->parser->parse([]));
        $this->assertNull($this->parser->parse(['not-empty' => 'value']));
    }

    public function testParseReturnsNullForMissingMetadata(): void
    {
        $payload = [
            [
                'metadata' => [],
                'lo' => [],
            ]
        ];

        $this->assertNull($this->parser->parse($payload));
    }

    public function testParseReturnsNullForMissingLoField(): void
    {
        $payload = [
            [
                'metadata' => [
                    'loUri' => 'lo://rdc/service-request/123',
                    'eventType' => 'create',
                ],
            ]
        ];

        $this->assertNull($this->parser->parse($payload));
    }

    /**
     * Test parsing with real webhook payload from SPEC (lines 162-380).
     * Verifies handling of notificationType, loUri extraction, eventType,
     * and nested address structure with addressLines[].
     */
    public function testParseWithRealSpecPayload(): void
    {
        $payload = [
            [
                'metadata' => [
                    'notificationType' => 'UPDATES',
                    'resourceType' => 'Service',
                    'loUri' => 'http://localhost:8080/services/ec1697f082',
                    'eventType' => 'create',
                    'eventDate' => '2026-03-03T13:41:25.137Z',
                    'consumerId' => '019c7ab7-e10a-7e5e-a36c-76c6e9a6156a',
                    'correlationId' => 'logistics_objects-service-1772545285137',
                    'loMemberIdentifier' => 'BOL.MEMBER.SHIPPER',
                    'loRevision' => 1,
                    'eventMemberIdentifier' => 'BOL.MEMBER.SHIPPER',
                ],
                'lo' => [
                    'id' => 'ec1697f082',
                    'executionStatus' => 'SCHEDULED',
                    'invoiceStatus' => 'NOT_INVOICED',
                    'isDangerous' => false,
                    'serviceAgreementReference' => 'livraison velo',
                    'serviceName' => 'livraison par velo ec1697f082',
                    'serviceNature' => 'LOGISTICS',
                    'serviceStatus' => 'APPROVED',
                    'serviceSubtype' => 'DELIVERY',
                    'serviceType' => 'TRANSPORT',
                    'startLocation' => [
                        'location' => [
                            'address' => [
                                'postalCode' => '49000',
                                'addressLines' => [
                                    '16 Bd sde l\'industrie ZI D'
                                ],
                                'addressRegion' => '',
                                'addressCountry' => [
                                    'countryCode' => 'FR',
                                    'countryName' => 'France'
                                ],
                                'addressLocality' => 'ECOUFLANT',
                                'postOfficeBoxNumber' => ''
                            ],
                            'locationName' => 'SHIPPER BUILDING',
                            'locationType' => ''
                        ],
                        'requestedStartTimeRange' => [
                            'latestDateTime' => '2025-12-26T07:30:00Z',
                            'earliestDateTime' => '2025-12-26T07:00:00Z'
                        ],
                        'requestedEndTimeRange' => [
                            'latestDateTime' => '2025-12-26T08:00:00Z',
                            'earliestDateTime' => '2025-12-26T07:30:00Z'
                        ],
                    ],
                    'endLocation' => [
                        'location' => [
                            'address' => [
                                'postalCode' => '49100',
                                'addressLines' => [
                                    '21 rue Jean Predali'
                                ],
                                'addressRegion' => '',
                                'addressCountry' => [
                                    'countryCode' => 'FR',
                                    'countryName' => 'France'
                                ],
                                'addressLocality' => 'ANGERS',
                                'postOfficeBoxNumber' => ''
                            ],
                            'locationName' => 'CONSIGNEE BUILDING',
                            'locationType' => ''
                        ],
                        'requestedStartTimeRange' => [
                            'latestDateTime' => '2025-12-26T09:30:59.999Z',
                            'earliestDateTime' => '2025-12-26T09:00:00Z'
                        ],
                        'requestedEndTimeRange' => [
                            'latestDateTime' => '2025-12-26T09:45:59.999Z',
                            'earliestDateTime' => '2025-12-26T09:15:00Z'
                        ],
                    ],
                    'contacts' => [
                        [
                            'role' => 'CUSTOMER_DISPATCH',
                            'email' => '',
                            'telephone' => '03.04.05.06.07'
                        ],
                        [
                            'role' => 'PROVIDER_DISPATCH',
                            'email' => '',
                            'telephone' => '01.02.03.04.05'
                        ]
                    ],
                    'externalReferences' => [
                        [
                            'reference' => 'ABC12548',
                            'description' => 'service n° ABC12548',
                            'externalReferenceType' => 'PROVIDER_ID'
                        ],
                        [
                            'reference' => 'C145237/2',
                            'description' => 'Service n° C145237/2',
                            'externalReferenceType' => 'REQUESTOR_ID'
                        ]
                    ],
                    'provider' => [
                        'legalEntityName' => 'SHIPPER'
                    ],
                    'requestor' => [
                        'legalEntityName' => 'SHIPPER'
                    ],
                    'incoterm' => 'DDP',
                ],
            ]
        ];

        $result = $this->parser->parse($payload);

        $this->assertNotNull($result);
        $this->assertEquals('http://localhost:8080/services/ec1697f082', $result['loUri']);
        $this->assertEquals('create', $result['eventType']);

        // Verify the lo object is extracted correctly with nested addressLines
        $this->assertArrayHasKey('lo', $result);
        $this->assertEquals('ec1697f082', $result['lo']['id']);
        $this->assertEquals('SCHEDULED', $result['lo']['executionStatus']);
        $this->assertEquals('TRANSPORT', $result['lo']['serviceType']);
        $this->assertEquals('LOGISTICS', $result['lo']['serviceNature']);

        // Verify nested address structure with addressLines
        $this->assertEquals('16 Bd sde l\'industrie ZI D', $result['lo']['startLocation']['location']['address']['addressLines'][0]);
        $this->assertEquals('21 rue Jean Predali', $result['lo']['endLocation']['location']['address']['addressLines'][0]);

        // Verify contacts are present
        $this->assertCount(2, $result['lo']['contacts']);
        $this->assertEquals('CUSTOMER_DISPATCH', $result['lo']['contacts'][0]['role']);
        $this->assertEquals('03.04.05.06.07', $result['lo']['contacts'][0]['telephone']);

        // Verify external references
        $this->assertCount(2, $result['lo']['externalReferences']);
        $this->assertEquals('PROVIDER_ID', $result['lo']['externalReferences'][0]['externalReferenceType']);
        $this->assertEquals('REQUESTOR_ID', $result['lo']['externalReferences'][1]['externalReferenceType']);
    }

    /**
     * Test that notificationType is present in parsed result's metadata structure.
     */
    public function testParseExtractsNotificationType(): void
    {
        $payload = [
            [
                'metadata' => [
                    'notificationType' => 'UPDATES',
                    'loUri' => 'http://localhost:8080/services/test123',
                    'eventType' => 'update',
                ],
                'lo' => [
                    'id' => 'test123',
                    'serviceType' => 'TRANSPORT',
                ],
            ]
        ];

        $result = $this->parser->parse($payload);

        $this->assertNotNull($result);
        $this->assertEquals('http://localhost:8080/services/test123', $result['loUri']);
        $this->assertEquals('update', $result['eventType']);
        // Verify the lo object is correctly extracted
        $this->assertEquals('test123', $result['lo']['id']);
        $this->assertEquals('TRANSPORT', $result['lo']['serviceType']);
    }
}