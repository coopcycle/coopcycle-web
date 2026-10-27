<?php

namespace Tests\AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestMapper;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Carbon\Carbon;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use ReflectionMethod;

class RdcServiceRequestMapperTest extends TestCase
{
    use ProphecyTrait;

    private const INSTANCE_TZ = 'Europe/Paris';

    // Service request from a provider sending a SHIPPER contact, special
    // characteristics, and the postal code/city apart from the address lines.
    private const SHIPPER_SERVICE_REQUEST_SAMPLE = <<<JSON
    {
        "requestStatus": "REQUESTED",
        "requestDateTime": "2026-11-02T09:00:00.000Z",
        "provider": {
            "legalEntityName": "COOPCYCLE"
        },
        "requestor": {
            "legalEntityName": "NOWASTEPHARMA"
        },
        "incoterm": "DDP",
        "isDangerous": false,
        "contacts": [
            {
                "role": "CUSTOMER_DISPATCH",
                "telephone": "0600000000",
                "email": "dispatch@example.com"
            },
            {
                "role": "SHIPPER",
                "telephone": "0600000001",
                "email": "contact@pharmacie-exemple.fr",
                "persons": [
                    {
                        "familyName": "Martin",
                        "givenName": "Claire",
                        "email": "contact@pharmacie-exemple.fr",
                        "telephone": "0600000001"
                    }
                ]
            },
            {
                "role": "RECIPIENT",
                "telephone": "0600000002",
                "email": "logistique@association-exemple.org",
                "persons": [
                    {
                        "familyName": "Bernard",
                        "givenName": "Hugo",
                        "email": "logistique@association-exemple.org",
                        "telephone": "0600000002"
                    }
                ]
            }
        ],
        "externalReferences": [
            {
                "description": "Collecte NoWaste n° C-26-1234-1",
                "reference": "C-26-1234-1",
                "externalReferenceType": "REQUESTOR_ID"
            },
            {
                "description": "Étiquette livraison",
                "reference": "C-26-1234-1",
                "externalReferenceType": "REQUESTOR_LABEL_ID"
            }
        ],
        "specialCharacteristics": [
            {
                "characteristicReference": "CUSTOMER_ORDER_DESCRIPTION",
                "stringValue": "6 cartons de produits pharmaceutiques (collecte C-26-1234-1)"
            }
        ],
        "startLocation": {
            "location": {
                "locationName": "Pharmacie du Marché",
                "address": {
                    "addressCountry": {
                        "countryCode": "FR",
                        "countryName": "France"
                    },
                    "addressLocality": "Lyon",
                    "postalCode": "69002",
                    "addressLines": [
                        "12 place du Marché"
                    ]
                }
            },
            "requestedStartTimeRange": {
                "earliestDateTime": "2026-11-06T07:30:00.000Z",
                "latestDateTime": "2026-11-06T18:30:00.000Z"
            },
            "requestedEndTimeRange": {
                "earliestDateTime": "2026-11-06T07:30:00.000Z",
                "latestDateTime": "2026-11-06T18:30:00.000Z"
            },
            "actions": [
                {
                    "actionName": "Chargement",
                    "actionState": "REQUESTED",
                    "actionType": "HANDLING",
                    "actionSubtype": "LOADING",
                    "sequenceNumber": 1,
                    "compositionMovements": [
                        {
                            "containedBatchesOfGoods": [
                                {
                                    "containerType": "PARCEL",
                                    "containerSubtype": "CARDBOARD",
                                    "quantity": {
                                        "value": 6,
                                        "unit": "CONTAINER"
                                    }
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        "endLocation": {
            "location": {
                "locationName": "Association Exemple",
                "address": {
                    "addressCountry": {
                        "countryCode": "FR",
                        "countryName": "France"
                    },
                    "addressLocality": "Villeurbanne",
                    "postalCode": "69100",
                    "addressLines": [
                        "8 rue de la Solidarité",
                        "Bâtiment B"
                    ]
                },
                "specialInstructions": [
                    {
                        "description": "Livraison par la cour, sonner au local du rez-de-chaussée."
                    }
                ]
            },
            "requestedStartTimeRange": {
                "earliestDateTime": "2026-11-06T08:00:00.000Z",
                "latestDateTime": "2026-11-06T16:00:00.000Z"
            },
            "requestedEndTimeRange": {
                "earliestDateTime": "2026-11-06T08:00:00.000Z",
                "latestDateTime": "2026-11-06T16:00:00.000Z"
            },
            "actions": [
                {
                    "actionName": "Déchargement",
                    "actionState": "REQUESTED",
                    "actionType": "HANDLING",
                    "actionSubtype": "UNLOADING",
                    "sequenceNumber": 1,
                    "compositionMovements": [
                        {
                            "containedBatchesOfGoods": [
                                {
                                    "containerType": "PARCEL",
                                    "containerSubtype": "CARDBOARD",
                                    "quantity": {
                                        "value": 6,
                                        "unit": "CONTAINER"
                                    }
                                }
                            ]
                        }
                    ]
                }
            ]
        }
    }
    JSON;

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

    public function testBuildCommentsFromSpecialInstructionsSkipsEntriesMissingDescription(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT', 'description' => 'Keep me'],
            ['instructionCode' => 'COMMENT'],
            ['instructionCode' => 'DIGICODE', 'description' => ''],
            ['instructionCode' => 'FLOOR', 'description' => '3'],
        ]);

        $this->assertSame("💬 COMMENT: Keep me\n🏢 FLOOR: 3", $comments);
    }

    public function testBuildCommentsFromSpecialInstructionsTreatsEntriesWithoutCodeAsComments(): void
    {
        $comments = $this->invokeBuildComments([
            ['description' => 'Livraison par la cour'],
            ['instructionCode' => 'FLOOR', 'description' => '3'],
        ]);

        $this->assertSame("💬 Livraison par la cour\n🏢 FLOOR: 3", $comments);
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullWhenAllEntriesAreInvalid(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT'],
            ['description' => ''],
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
        // No separate postal code/city: the address line is kept as is.
        $this->assertSame('Place des Grands Hommes, Bordeaux, France', $delivery->getPickup()->getAddress()->getStreetAddress());
        $this->assertNull($delivery->getPickup()->getAddress()->getDescription());
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

    public function testMapToDeliveryMapsShipperPayload(): void
    {
        $apiRequest = \AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest::parse(
            json_decode(self::SHIPPER_SERVICE_REQUEST_SAMPLE, true, flags: JSON_THROW_ON_ERROR)
        );

        // The postal code and city are sent apart: they must reach the geocoder.
        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->geocode('12 place du Marché, 69002 Lyon')->shouldBeCalled()->willReturn(null);
        $geocoder->geocode('8 rue de la Solidarité, 69100 Villeurbanne')->shouldBeCalled()->willReturn(null);

        $phoneUtil = PhoneNumberUtil::getInstance();
        $mapper = new RdcServiceRequestMapper(
            $this->prophesize(DeliveryManager::class)->reveal(),
            $geocoder->reveal(),
            $phoneUtil,
            new NullLogger(),
        );

        $delivery = $mapper->mapToDelivery($apiRequest, new \AppBundle\Entity\Store());

        $this->assertSame('C-26-1234-1', $delivery->getExternalReference());

        $pickupAddress = $delivery->getPickup()->getAddress();
        $this->assertSame('Pharmacie du Marché', $pickupAddress->getName());
        $this->assertSame('12 place du Marché, 69002 Lyon', $pickupAddress->getStreetAddress());
        $this->assertSame('Claire Martin', $pickupAddress->getContactName());
        $this->assertSame('+33600000001', $phoneUtil->format($pickupAddress->getTelephone(), PhoneNumberFormat::E164));

        $dropoffAddress = $delivery->getDropoff()->getAddress();
        $this->assertSame('Association Exemple', $dropoffAddress->getName());
        $this->assertSame('8 rue de la Solidarité, 69100 Villeurbanne', $dropoffAddress->getStreetAddress());
        $this->assertSame('Bâtiment B', $dropoffAddress->getDescription());
        $this->assertSame('Hugo Bernard', $dropoffAddress->getContactName());
        $this->assertSame('+33600000002', $phoneUtil->format($dropoffAddress->getTelephone(), PhoneNumberFormat::E164));

        $orderDescription = '📦 CUSTOMER_ORDER_DESCRIPTION: 6 cartons de produits pharmaceutiques (collecte C-26-1234-1)';
        $this->assertSame($orderDescription, $delivery->getPickup()->getComments());
        $this->assertSame(
            sprintf("%s\n💬 Livraison par la cour, sonner au local du rez-de-chaussée.", $orderDescription),
            $delivery->getDropoff()->getComments()
        );
    }

    public function testBuildCommentsFromSpecialCharacteristics(): void
    {
        $method = new ReflectionMethod($this->mapper, 'buildCommentsFromSpecialCharacteristics');

        $this->assertNull($method->invoke($this->mapper, null));
        $this->assertNull($method->invoke($this->mapper, [['characteristicReference' => 'EMPTY']]));
        $this->assertSame(
            "⭐ FRAGILE: oui\n⭐ VOLUME: 0.5 M3\n⭐ Sans libellé",
            $method->invoke($this->mapper, [
                // The type wins over the reference
                ['specialCharacteristicType' => 'FRAGILE', 'characteristicReference' => 'REF-1', 'stringValue' => 'oui'],
                ['characteristicReference' => 'VOLUME', 'numericalValue' => ['value' => 0.5, 'unit' => 'M3']],
                ['stringValue' => 'Sans libellé'],
            ])
        );
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
