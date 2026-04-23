<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\DTO\ServiceRequest;
use AppBundle\Integration\Rdc\DTO\ServiceRequestAddress;
use AppBundle\Integration\Rdc\DTO\ServiceRequestContact;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Integration\Rdc\Mapper\ServiceRequestMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ServiceRequestMapperTest extends TestCase
{
    private ServiceRequestMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ServiceRequestMapper();
    }

    public function testMapExtractsPickupAndDropoffAddresses(): void
    {
        $serviceRequest = [
            'startLocation' => [
                'location' => [
                    'address' => [
                        'streetAddress' => '123 Pickup St',
                        'postalCode' => '75001',
                        'city' => 'Paris',
                        'country' => 'FR',
                    ]
                ],
                'requestedStartTimeRange' => [
                    'earliestDateTime' => '2024-01-01T10:00:00Z'
                ],
                'requestedEndTimeRange' => [
                    'latestDateTime' => '2024-01-01T12:00:00Z'
                ]
            ],
            'endLocation' => [
                'location' => [
                    'address' => [
                        'streetAddress' => '456 Dropoff Ave',
                        'postalCode' => '75002',
                        'city' => 'Lyon',
                        'country' => 'FR',
                    ]
                ],
                'requestedStartTimeRange' => [
                    'earliestDateTime' => '2024-01-01T14:00:00Z'
                ],
                'requestedEndTimeRange' => [
                    'latestDateTime' => '2024-01-01T16:00:00Z'
                ]
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertInstanceOf(ServiceRequest::class, $result);

        $this->assertEquals('123 Pickup St', $result->addresses['pickup']->streetAddress);
        $this->assertEquals('Paris', $result->addresses['pickup']->city);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->timeSlots['pickup']->start);

        $this->assertEquals('456 Dropoff Ave', $result->addresses['dropoff']->streetAddress);
        $this->assertEquals('Lyon', $result->addresses['dropoff']->city);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->timeSlots['dropoff']->end);
    }

    public function testMapExtractsExternalReferences(): void
    {
        $serviceRequest = [
            'externalReferences' => [
                ['type' => 'REQUESTOR_ID', 'reference' => 'ORDER-123'],
                ['type' => 'REQUESTOR_LABEL_ID', 'reference' => 'BARCODE-456'],
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertEquals('ORDER-123', $result->externalRef);
        $this->assertEquals('BARCODE-456', $result->barcode);
    }

    public function testMapExtractsRecipientContact(): void
    {
        $serviceRequest = [
            'contacts' => [
                ['role' => 'SENDER', 'name' => 'Sender Name'],
                ['role' => 'RECIPIENT', 'name' => 'Recipient Name', 'phone' => '+33612345678', 'email' => 'recipient@test.com'],
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertArrayHasKey('RECIPIENT', $result->contacts);
        $recipient = $result->contacts['RECIPIENT'];
        $this->assertInstanceOf(ServiceRequestContact::class, $recipient);
        $this->assertEquals('Recipient Name', $recipient->name);
        $this->assertEquals('+33612345678', $recipient->phone);
        $this->assertEquals('recipient@test.com', $recipient->email);
    }

    public function testMapExtractsContractReference(): void
    {
        $serviceRequest = [
            'serviceAgreementReference' => 'CONTRACT-789',
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertEquals('CONTRACT-789', $result->contractRef);
    }

    public function testMapHandlesEmptyServiceRequest(): void
    {
        $result = $this->mapper->map([]);

        $this->assertNull($result->externalRef);
        $this->assertNull($result->barcode);
        $this->assertNull($result->contractRef);
        $this->assertEmpty($result->contacts);
        $this->assertEmpty($result->addresses['pickup']->streetAddress);
        $this->assertEmpty($result->addresses['dropoff']->streetAddress);
    }

    public function testMapExtractsAddressWithAddressLinesFormat(): void
    {
        $serviceRequest = [
            'startLocation' => [
                'location' => [
                    'address' => [
                        'addressLines' => ['16 Bd sde l\'industrie ZI D'],
                        'postalCode' => '49000',
                        'addressLocality' => 'ECOUFLANT',
                        'addressCountry' => ['countryCode' => 'FR', 'countryName' => 'France']
                    ]
                ],
            ],
            'endLocation' => [
                'location' => [
                    'address' => [
                        'addressLines' => ['123 Rue Test', 'Batiment A'],
                        'postalCode' => '69001',
                        'addressLocality' => 'LYON',
                        'addressCountry' => ['countryCode' => 'FR', 'countryName' => 'France']
                    ]
                ],
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        // Check pickup address from addressLines
        $this->assertEquals("16 Bd sde l'industrie ZI D", $result->addresses['pickup']->streetAddress);
        $this->assertEquals('49000', $result->addresses['pickup']->postalCode);
        $this->assertEquals('ECOUFLANT', $result->addresses['pickup']->city);
        $this->assertEquals('FR', $result->addresses['pickup']->country);

        // Check dropoff address from addressLines
        $this->assertEquals('123 Rue Test Batiment A', $result->addresses['dropoff']->streetAddress);
        $this->assertEquals('69001', $result->addresses['dropoff']->postalCode);
        $this->assertEquals('LYON', $result->addresses['dropoff']->city);
        $this->assertEquals('FR', $result->addresses['dropoff']->country);
    }

    public function testMapExtractsCustomerDispatchContact(): void
    {
        $serviceRequest = [
            'contacts' => [
                ['role' => 'CUSTOMER_DISPATCH', 'name' => 'Dispatch Name', 'phone' => '+33698765432', 'email' => 'dispatch@test.com'],
                ['role' => 'RECIPIENT', 'name' => 'Recipient Name', 'phone' => '+33612345678', 'email' => 'recipient@test.com'],
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        // Verify recipient is still extracted
        $this->assertArrayHasKey('RECIPIENT', $result->contacts);
        $this->assertEquals('Recipient Name', $result->contacts['RECIPIENT']->name);

        // Verify dispatcher contact is extracted
        $this->assertArrayHasKey('CUSTOMER_DISPATCH', $result->contacts);
        $dispatcher = $result->contacts['CUSTOMER_DISPATCH'];
        $this->assertInstanceOf(ServiceRequestContact::class, $dispatcher);
        $this->assertEquals('Dispatch Name', $dispatcher->name);
        $this->assertEquals('+33698765432', $dispatcher->phone);
        $this->assertEquals('dispatch@test.com', $dispatcher->email);
    }

    public function testMapHandlesPartialAddressWithAddressLines(): void
    {
        $serviceRequest = [
            'startLocation' => [
                'location' => [
                    'address' => [
                        'addressLines' => ['16 Bd sde l\'industrie'],
                    ]
                ],
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertEquals("16 Bd sde l'industrie", $result->addresses['pickup']->streetAddress);
        $this->assertEquals('', $result->addresses['pickup']->postalCode);
        $this->assertEquals('', $result->addresses['pickup']->city);
        $this->assertEquals('', $result->addresses['pickup']->country);
    }

    public function testMapHandlesEmptyContactsArray(): void
    {
        $serviceRequest = [
            'contacts' => [],
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertEmpty($result->contacts);
    }

    public function testMapHandlesContactsWithoutRoles(): void
    {
        $serviceRequest = [
            'contacts' => [
                ['name' => 'Some Person'],
            ],
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertEmpty($result->contacts);
    }

    /**
     * Test mapping with real service-request payload from SPEC (lines 626-785).
     * Uses actual address structure with addressLines, addressCountry, addressLocality.
     * Tests CUSTOMER_DISPATCH contact extraction, multiple external references,
     * serviceAgreementReference mapping, and nested time ranges.
     */
    public function testMapWithRealSpecServiceRequestPayload(): void
    {
        $serviceRequest = [
            'requestStatus' => 'REQUESTED',
            'requestDateTime' => '2025-12-23T09:00:00.000Z',
            'provider' => ['legalEntityName' => 'CARGOBIKE'],
            'requestor' => ['legalEntityName' => 'SHIPPER'],
            'incoterm' => 'DDP',
            'isDangerous' => false,
            'serviceAgreementReference' => '45687G/1.12',
            'contacts' => [
                [
                    'role' => 'CUSTOMER_DISPATCH',
                    'telephone' => '03.04.05.06.07',
                    'email' => ''
                ],
                [
                    'role' => 'PROVIDER_DISPATCH',
                    'telephone' => '01.02.03.04.05',
                    'email' => ''
                ]
            ],
            'externalReferences' => [
                [
                    'description' => 'Service request n° C145237/1 for shipper',
                    'reference' => 'C145237/1',
                    'externalReferenceType' => 'CUSTOMER_ID'
                ]
            ],
            'startLocation' => [
                'location' => [
                    'address' => [
                        'addressCountry' => [
                            'countryCode' => 'FR',
                            'countryName' => 'France'
                        ],
                        'addressLocality' => 'ECOUFLANT',
                        'postalCode' => '49000',
                        'addressLines' => [
                            '16 Bd sde l\'industrie ZI D'
                        ]
                    ],
                    'locationName' => 'SHIPPER BUILDING'
                ],
                'requestedStartTimeRange' => [
                    'earliestDateTime' => '2025-12-26T07:00:00.000Z',
                    'latestDateTime' => '2025-12-26T07:30:00.000Z'
                ],
                'requestedEndTimeRange' => [
                    'earliestDateTime' => '2025-12-26T07:30:00.000Z',
                    'latestDateTime' => '2025-12-26T08:00:00.000Z'
                ],
                'actions' => [
                    [
                        'actionName' => 'Loading',
                        'actionState' => 'REQUESTED',
                        'actionType' => 'HANDLING',
                        'actionSubtype' => 'LOADING',
                        'sequenceNumber' => 1
                    ]
                ]
            ],
            'endLocation' => [
                'location' => [
                    'address' => [
                        'addressCountry' => [
                            'countryCode' => 'FR',
                            'countryName' => 'France'
                        ],
                        'addressLocality' => 'ANGERS',
                        'postalCode' => '49100',
                        'addressLines' => [
                            '21 rue Jean Predali'
                        ]
                    ],
                    'locationName' => 'CONSIGNEE BUILDING'
                ],
                'requestedStartTimeRange' => [
                    'earliestDateTime' => '2025-12-26T09:00:00.000Z',
                    'latestDateTime' => '2025-12-26T09:30:59.999Z'
                ],
                'requestedEndTimeRange' => [
                    'earliestDateTime' => '2025-12-26T09:15:00.000Z',
                    'latestDateTime' => '2025-12-26T09:45:59.999Z'
                ],
                'actions' => [
                    [
                        'actionName' => 'Unloading',
                        'actionState' => 'REQUESTED',
                        'actionType' => 'HANDLING',
                        'actionSubtype' => 'UNLOADING',
                        'sequenceNumber' => 1
                    ]
                ]
            ]
        ];

        $result = $this->mapper->map($serviceRequest);

        $this->assertInstanceOf(ServiceRequest::class, $result);

        // Verify contract reference mapping
        $this->assertEquals('45687G/1.12', $result->contractRef);

        // Verify pickup address with addressLines structure
        $pickupAddress = $result->addresses['pickup'];
        $this->assertEquals("16 Bd sde l'industrie ZI D", $pickupAddress->streetAddress);
        $this->assertEquals('49000', $pickupAddress->postalCode);
        $this->assertEquals('ECOUFLANT', $pickupAddress->city);
        $this->assertEquals('FR', $pickupAddress->country);

        // Verify dropoff address with addressLines structure
        $dropoffAddress = $result->addresses['dropoff'];
        $this->assertEquals('21 rue Jean Predali', $dropoffAddress->streetAddress);
        $this->assertEquals('49100', $dropoffAddress->postalCode);
        $this->assertEquals('ANGERS', $dropoffAddress->city);
        $this->assertEquals('FR', $dropoffAddress->country);

        // Verify CUSTOMER_DISPATCH contact extraction
        $this->assertArrayHasKey('CUSTOMER_DISPATCH', $result->contacts);
        $customerDispatch = $result->contacts['CUSTOMER_DISPATCH'];
        $this->assertInstanceOf(ServiceRequestContact::class, $customerDispatch);
        $this->assertEquals('03.04.05.06.07', $customerDispatch->phone);
        $this->assertEquals('', $customerDispatch->email);

        // Verify external references (CUSTOMER_ID maps to externalRef via REQUESTOR_ID enum)
        $this->assertEquals('C145237/1', $result->externalRef);

        // Verify time slots are extracted correctly
        $pickupSlot = $result->timeSlots['pickup'];
        $this->assertInstanceOf(TimeSlot::class, $pickupSlot);
        $this->assertNotNull($pickupSlot->start);
        $this->assertNotNull($pickupSlot->end);
        $this->assertEquals('2025-12-26', $pickupSlot->start->format('Y-m-d'));
        $this->assertEquals('07:00:00', $pickupSlot->start->format('H:i:s'));

        $dropoffSlot = $result->timeSlots['dropoff'];
        $this->assertInstanceOf(TimeSlot::class, $dropoffSlot);
        $this->assertNotNull($dropoffSlot->start);
        $this->assertNotNull($dropoffSlot->end);
        $this->assertEquals('2025-12-26', $dropoffSlot->start->format('Y-m-d'));
        $this->assertEquals('09:00:00', $dropoffSlot->start->format('H:i:s'));
    }

    /**
     * Test mapping with external references using REQUESTOR_ID and PROVIDER_ID types.
     */
    public function testMapWithMultipleExternalReferenceTypes(): void
    {
        $serviceRequest = [
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
        ];

        $result = $this->mapper->map($serviceRequest);

        // REQUESTOR_ID maps to externalRef
        $this->assertEquals('C145237/2', $result->externalRef);
    }
}