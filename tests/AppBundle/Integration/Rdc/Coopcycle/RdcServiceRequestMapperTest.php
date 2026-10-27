<?php

namespace Tests\AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestMapper;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use ReflectionMethod;

class RdcServiceRequestMapperTest extends TestCase
{
    use ProphecyTrait;

    private const INSTANCE_TZ = 'Europe/Paris';

    private RdcServiceRequestMapper $mapper;

    protected function setUp(): void
    {
        $previousTz = date_default_timezone_get();
        date_default_timezone_set(self::INSTANCE_TZ);

        $this->mapper = new RdcServiceRequestMapper(
            $this->prophesize(DeliveryManager::class)->reveal(),
            $this->prophesize(Geocoder::class)->reveal(),
            $this->prophesize(PhoneNumberUtil::class)->reveal(),
            new NullLogger(),
        );

        // Restore on tearDown
        register_shutdown_function(static fn () => date_default_timezone_set($previousTz));
    }

    public function testMapTimeSlotReturnsUtcAnchoredDateTimes(): void
    {
        $slot = new TimeSlot(
            start: Carbon::parse('2024-06-01T08:00:00Z')->utc()->toDateTimeImmutable(),
            end: Carbon::parse('2024-06-01T10:00:00Z')->utc()->toDateTimeImmutable(),
        );

        $result = $this->mapper->mapTimeSlot($slot);

        $this->assertSame('UTC', $result['start']->getTimezone()->getName());
        $this->assertSame('UTC', $result['end']->getTimezone()->getName());
        $this->assertSame('2024-06-01T08:00:00+00:00', $result['start']->format('c'));
        $this->assertSame('2024-06-01T10:00:00+00:00', $result['end']->format('c'));
    }

    public function testSetTaskTimeRangeConvertsUtcInstantToLocalTimezone(): void
    {
        // 2024-06-01T08:00:00Z = 10:00:00 in Europe/Paris (CEST, UTC+2)
        $slot = new TimeSlot(
            start: Carbon::parse('2024-06-01T08:00:00Z')->utc()->toDateTimeImmutable(),
            end: Carbon::parse('2024-06-01T10:00:00Z')->utc()->toDateTimeImmutable(),
        );

        $task = new Task();

        $setTaskTimeRange = new ReflectionMethod($this->mapper, 'setTaskTimeRange');
        $setTaskTimeRange->invoke($this->mapper, $task, $slot);

        $this->assertSame(self::INSTANCE_TZ, $task->getDoneAfter()->getTimezone()->getName());
        $this->assertSame(self::INSTANCE_TZ, $task->getDoneBefore()->getTimezone()->getName());
        $this->assertSame('2024-06-01T10:00:00+02:00', $task->getDoneAfter()->format('c'));
        $this->assertSame('2024-06-01T12:00:00+02:00', $task->getDoneBefore()->format('c'));
    }

    public function testSetTaskTimeRangeSkipsNullBounds(): void
    {
        $slot = new TimeSlot(start: null, end: null);

        $task = new Task();

        $setTaskTimeRange = new ReflectionMethod($this->mapper, 'setTaskTimeRange');
        $setTaskTimeRange->invoke($this->mapper, $task, $slot);

        $this->assertNull($task->getDoneAfter());
        $this->assertNull($task->getDoneBefore());
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullForNullInput(): void
    {
        $this->assertNull($this->invokeBuildComments(null));
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullForEmptyArray(): void
    {
        $this->assertNull($this->invokeBuildComments([]));
    }

    public function testBuildCommentsFromSpecialInstructionsFormatsKnownCodes(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT', 'description' => 'Porte garage bois bleue'],
            ['instructionCode' => 'DIGICODE', 'description' => '10345A'],
            ['instructionCode' => 'FLOOR', 'description' => '1'],
        ]);

        $this->assertSame(
            "💬 COMMENT: Porte garage bois bleue\n🔢 DIGICODE: 10345A\n🏢 FLOOR: 1",
            $comments
        );
    }

    public function testBuildCommentsFromSpecialInstructionsUsesPinForUnknownCode(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'SOMETHING_ELSE', 'description' => 'Whatever'],
        ]);

        $this->assertSame('📌 SOMETHING_ELSE: Whatever', $comments);
    }

    public function testBuildCommentsFromSpecialInstructionsSkipsEntriesMissingCodeOrDescription(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT', 'description' => 'Keep me'],
            ['instructionCode' => 'COMMENT'],
            ['description' => 'No code here'],
            ['instructionCode' => 'DIGICODE', 'description' => ''],
            ['instructionCode' => 'FLOOR', 'description' => '3'],
        ]);

        $this->assertSame("💬 COMMENT: Keep me\n🏢 FLOOR: 3", $comments);
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullWhenAllEntriesAreInvalid(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT'],
            ['description' => 'No code'],
            ['instructionCode' => 'DIGICODE', 'description' => ''],
        ]);

        $this->assertNull($comments);
    }

    public function testExtractWeightFromLocationReturnsNullForNullInput(): void
    {
        $this->assertNull($this->invokeExtractWeight(null));
    }

    public function testExtractWeightFromLocationReturnsNullForEmptyArray(): void
    {
        $this->assertNull($this->invokeExtractWeight([]));
    }

    public function testExtractWeightFromLocationReturnsNullWhenActionsKeyMissing(): void
    {
        $this->assertNull($this->invokeExtractWeight(['location' => ['address' => []]]));
    }

    public function testExtractWeightFromLocationReturnsNullWhenNoBatches(): void
    {
        $this->assertNull($this->invokeExtractWeight(['actions' => []]));
    }

    public function testExtractWeightFromLocationHandlesNullActionEntry(): void
    {
        $location = [
            'actions' => [
                null,
                [
                    'compositionMovements' => [
                        ['containedBatchesOfGoods' => [['weight' => ['value' => 3, 'unit' => 'KG']]]],
                    ],
                ],
            ],
        ];

        $this->assertSame(3000, $this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationHandlesNullCompositionMovementEntry(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        null,
                        ['containedBatchesOfGoods' => [['weight' => ['value' => 4, 'unit' => 'KG']]]],
                    ],
                ],
            ],
        ];

        $this->assertSame(4000, $this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationHandlesNullBatchEntry(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                null,
                                ['weight' => ['value' => 6, 'unit' => 'KG']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(6000, $this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSkipsBatchWithoutWeightKey(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['containerType' => 'PARCEL', 'containerSubtype' => 'CARDBOARD'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertNull($this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSkipsExplicitNullWeight(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => null],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertNull($this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSkipsWeightMissingValueKey(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['unit' => 'KG']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertNull($this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSumsAcrossMultipleActions(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['value' => 1, 'unit' => 'KG']],
                                ['weight' => ['value' => 2, 'unit' => 'KG']],
                            ],
                        ],
                    ],
                ],
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['value' => 3, 'unit' => 'KG']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(6000, $this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSumsAllKgBatchesInGrams(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['value' => 5, 'unit' => 'KG']],
                                ['weight' => ['value' => 7.5, 'unit' => 'KG']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(12500, $this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationTreatsMissingUnitAsKg(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['value' => 2]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(2000, $this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSkipsNonKgUnits(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['value' => 100, 'unit' => 'LB']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertNull($this->invokeExtractWeight($location));
    }

    public function testExtractWeightFromLocationSkipsNonNumericValues(): void
    {
        $location = [
            'actions' => [
                [
                    'compositionMovements' => [
                        [
                            'containedBatchesOfGoods' => [
                                ['weight' => ['value' => 'not-a-number', 'unit' => 'KG']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertNull($this->invokeExtractWeight($location));
    }

    public function testMapToDeliveryWritesRdcProvenanceToDeliveryMetadata(): void
    {
        $apiRequest = new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            uri: 'https://rdc.example.com/lo/123',
            externalReferences: [
                ['externalReferenceType' => 'REQUESTOR_LABEL_ID', 'reference' => 'TEST-BARCODE'],
            ],
            startLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Paris',
                        'postalCode' => '75001',
                        'addressLines' => ['1 rue de Rivoli'],
                    ],
                ],
            ],
            endLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Lyon',
                        'postalCode' => '69001',
                        'addressLines' => ['1 place Bellecour'],
                    ],
                ],
            ],
        );

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $deliveryMetadata = $delivery->getMetadata();
        $this->assertSame('https://rdc.example.com/lo/123', $deliveryMetadata['rdc']['lo_uri']);
        $this->assertArrayHasKey('created_at', $deliveryMetadata['rdc']);

        // Provenance no longer lives on the pickup task metadata
        $pickupMetadata = $delivery->getPickup()->getMetadata();
        $this->assertArrayNotHasKey('rdc_lo_uri', $pickupMetadata);
        $this->assertArrayNotHasKey('rdc_created_at', $pickupMetadata);
    }

    public function testMapToDeliveryWritesLabelIdToExternalReference(): void
    {
        $apiRequest = $this->buildApiRequestWithBarcode('TEST-001');

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertSame('TEST-001', $delivery->getExternalReference());
        $this->assertSame('TEST-001', $delivery->getMetadata()['external_reference']);
        // Mirrored on both tasks, so it shows up wherever the task does.
        $this->assertSame('TEST-001', $delivery->getPickup()->getMetadata()['external_reference']);
        $this->assertSame('TEST-001', $delivery->getDropoff()->getMetadata()['external_reference']);
        // The label id is no longer stored as the pickup barcode.
        $pickupMetadata = $delivery->getPickup()->getMetadata();
        $this->assertArrayNotHasKey('barcode', $pickupMetadata);
        $this->assertArrayNotHasKey('rdc_barcode', $pickupMetadata);
    }

    public function testMapToDeliveryPrefersLabelIdOverRequestorId(): void
    {
        $apiRequest = new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            externalReferences: [
                ['externalReferenceType' => 'REQUESTOR_LABEL_ID', 'reference' => 'TEST-BARCODE'],
                ['externalReferenceType' => 'REQUESTOR_ID', 'reference' => 'EXT-REF-42'],
            ],
            startLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Paris',
                        'postalCode' => '75001',
                        'addressLines' => ['1 rue de Rivoli'],
                    ],
                ],
            ],
            endLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Paris',
                        'postalCode' => '75002',
                        'addressLines' => ['2 rue de la Paix'],
                    ],
                ],
            ],
        );

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        // The label id wins over the requestor id, which is kept under `rdc`.
        $this->assertSame('TEST-BARCODE', $delivery->getExternalReference());
        $this->assertSame('EXT-REF-42', $delivery->getMetadata()['rdc']['requestor_id']);
        // Legacy metadata keys are gone.
        $pickupMetadata = $delivery->getPickup()->getMetadata();
        $this->assertArrayNotHasKey('rdc_external_ref', $pickupMetadata);
        $this->assertArrayNotHasKey('rdc_contract_ref', $pickupMetadata);
    }

    public function testMapToDeliveryLeavesExternalReferenceNullWithoutLabelId(): void
    {
        // Only REQUESTOR_ID present, no REQUESTOR_LABEL_ID.
        $base = $this->buildApiRequestWithBarcode('unused');
        $apiRequest = new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            externalReferences: [
                ['externalReferenceType' => 'REQUESTOR_ID', 'reference' => 'EXT-REF-ONLY'],
            ],
            startLocation: $base->startLocation,
            endLocation: $base->endLocation,
        );

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        // No fallback: the requestor id is only kept under `rdc`.
        $this->assertNull($delivery->getExternalReference());
        $this->assertArrayNotHasKey('external_reference', $delivery->getPickup()->getMetadata());
        $this->assertSame('EXT-REF-ONLY', $delivery->getMetadata()['rdc']['requestor_id']);
    }

    public function testMapToDeliveryDoesNotStoreRequestorIdWhenAbsent(): void
    {
        $apiRequest = $this->buildApiRequestWithBarcode('TEST-ONLY-BARCODE');

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertArrayNotHasKey('requestor_id', $delivery->getMetadata()['rdc']);
    }

    public function testMapToDeliverySetsWeightOnPickupFromStartLocationBatches(): void
    {
        $apiRequest = $this->buildApiRequestWithBarcode('TEST-WEIGHT');
        $apiRequest = new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            id: $apiRequest->id,
            uri: $apiRequest->uri,
            externalReferences: $apiRequest->externalReferences,
            startLocation: [
                'actions' => [
                    [
                        'compositionMovements' => [
                            [
                                'containedBatchesOfGoods' => [
                                    ['weight' => ['value' => 5, 'unit' => 'KG']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            endLocation: $apiRequest->endLocation,
        );

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertSame(5000, $delivery->getPickup()->getWeight());
        // Only on the pickup: Delivery::getWeight() sums all tasks.
        $this->assertNull($delivery->getDropoff()->getWeight());
        $this->assertSame(5000, $delivery->getWeight());
    }

    public function testMapToDeliveryLeavesWeightNullWhenPayloadHasNoWeight(): void
    {
        $apiRequest = $this->buildApiRequestWithBarcode('TEST-NO-WEIGHT');

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertNull($delivery->getPickup()->getWeight());
        $this->assertNull($delivery->getDropoff()->getWeight());
    }

    public function testMapToDeliveryLeavesWeightNullWhenStartLocationIsNull(): void
    {
        $apiRequest = new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            externalReferences: [
                ['externalReferenceType' => 'REQUESTOR_LABEL_ID', 'reference' => 'TEST-NULL-LOC'],
            ],
            endLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Lyon',
                        'postalCode' => '69001',
                        'addressLines' => ['1 place Bellecour'],
                    ],
                ],
            ],
        );

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertNull($delivery->getPickup()->getWeight());
        $this->assertNull($delivery->getDropoff()->getWeight());
    }

    private function invokeBuildComments(?array $specialInstructions): ?string
    {
        $method = new ReflectionMethod($this->mapper, 'buildCommentsFromSpecialInstructions');
        return $method->invoke($this->mapper, $specialInstructions);
    }

    public function testMapToDeliveryUsesLocationNamesFromApplicolisPayload(): void
    {
        // Shaped like what Applicolis sends: the RECIPIENT contact has no name,
        // the names are on the locations.
        $apiRequest = \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest::parse([
            'startLocation' => [
                'location' => [
                    'locationName' => 'C.Market Grands Hommes',
                    'address' => [
                        'addressLines' => ['Place des Grands Hommes, Bordeaux, France'],
                        'addressCountry' => ['countryCode' => 'FR'],
                    ],
                ],
            ],
            'endLocation' => [
                'location' => [
                    'locationName' => 'JANE DOE',
                    'address' => [
                        'addressLines' => ["Rue d'Aviau 1 33000 Bordeaux France"],
                        'addressCountry' => ['countryCode' => 'FR'],
                    ],
                ],
            ],
            'contacts' => [
                ['email' => 'jane.doe@example.com', 'role' => 'RECIPIENT'],
            ],
            'externalReferences' => [
                ['externalReferenceType' => 'REQUESTOR_LABEL_ID', 'reference' => '637900000'],
            ],
        ]);

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertSame('C.Market Grands Hommes', $delivery->getPickup()->getAddress()->getName());
        $this->assertSame('JANE DOE', $delivery->getDropoff()->getAddress()->getName());
        $this->assertSame('JANE DOE', $delivery->getDropoff()->getAddress()->getContactName());
    }

    public function testMapToDeliveryPrefersRecipientContactNameOverLocationName(): void
    {
        $base = $this->buildApiRequestWithBarcode('TEST-CONTACT');
        $endLocation = $base->endLocation;
        $endLocation['location']['locationName'] = 'ACME Corp';

        $apiRequest = new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            contacts: [
                ['familyName' => 'Doe', 'role' => 'RECIPIENT'],
            ],
            externalReferences: $base->externalReferences,
            startLocation: $base->startLocation,
            endLocation: $endLocation,
        );

        $delivery = $this->mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertSame('ACME Corp', $delivery->getDropoff()->getAddress()->getName());
        $this->assertSame('Doe', $delivery->getDropoff()->getAddress()->getContactName());
    }

    private function invokeExtractWeight(?array $location): ?int
    {
        $method = new ReflectionMethod($this->mapper, 'extractWeightFromLocation');
        return $method->invoke($this->mapper, $location);
    }

    private function buildApiRequestWithBarcode(string $barcode): \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest
    {
        return new \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest(
            externalReferences: [
                ['externalReferenceType' => 'REQUESTOR_LABEL_ID', 'reference' => $barcode],
            ],
            startLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Paris',
                        'postalCode' => '75001',
                        'addressLines' => ['1 rue de Rivoli'],
                    ],
                ],
            ],
            endLocation: [
                'location' => [
                    'address' => [
                        'addressCountry' => ['countryCode' => 'FR'],
                        'addressLocality' => 'Lyon',
                        'postalCode' => '69001',
                        'addressLines' => ['1 place Bellecour'],
                    ],
                ],
            ],
        );
    }
}
