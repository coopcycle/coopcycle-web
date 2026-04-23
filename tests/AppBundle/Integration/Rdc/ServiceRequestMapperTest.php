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
}