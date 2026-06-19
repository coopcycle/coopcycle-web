<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Integration\Rdc\DTO\ServiceRequestAddress;
use AppBundle\Integration\Rdc\DTO\ServiceRequestContact;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use DateTimeInterface;
use DateTime;

class RdcServiceRequestMapper
{
    public function __construct(
        private DeliveryManager $deliveryManager,
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneUtil,
        private LoggerInterface $logger,
    ) {}

    public function mapToDelivery(
        RdcApiServiceRequest $apiRequest,
        Store $store,
        ?string $originNodeUri = null
    ): Delivery {
        $this->validateForCreate($apiRequest);
        $loUri = $originNodeUri ?? $apiRequest->getUri();

        $delivery = new Delivery();
        $delivery->setStore($store);
        $store->addDelivery($delivery);

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        // Pickup
        $pickupAddress = $apiRequest->getPickupAddress();
        if (!$pickupAddress->isEmpty()) {
            $pickup->setAddress($this->createAddress($pickupAddress));
            $this->setTaskTimeRange($pickup, $apiRequest->getPickupTimeSlot());
        }

        // Dropoff
        $dropoffAddress = $apiRequest->getDropoffAddress();
        if (!$dropoffAddress->isEmpty()) {
            $dropoff->setAddress($this->createAddress($dropoffAddress));
            $this->setTaskTimeRange($dropoff, $apiRequest->getDropoffTimeSlot());
        }

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $this->deliveryManager->setDefaults($delivery);

        // RDC metadata
        $pickup->setMetadata([
            'rdc_lo_uri' => $loUri,
            'rdc_external_ref' => $apiRequest->getExternalRef(),
            'rdc_contract_ref' => $apiRequest->getContractRef(),
            'rdc_created_at' => (new DateTime())->format(DateTimeInterface::ATOM),
        ]);

        $barcode = $apiRequest->getBarcode();
        if (!is_null($barcode)) {
            $pickup->setBarcode($barcode);
        }

        $weightGrams = $this->extractWeightFromLocation($apiRequest->startLocation);
        if (!is_null($weightGrams)) {
            $dropoff->setWeight($weightGrams);
        }

        // Dropoff comments from special instructions
        $dropoffComments = $this->buildCommentsFromSpecialInstructions($apiRequest->specialInstructions);
        if (!is_null($dropoffComments)) {
            $dropoff->setComments($dropoffComments);
        }

        // Recipient contact
        $recipient = $apiRequest->getRecipientContact();
        if ($this->isContactNotEmpty($recipient)) {
            $addr = $dropoff->getAddress();
            if ($recipient->name) {
                $addr->setContactName($recipient->name);
            }
            if ($recipient->phone) {
                try {
                    $phoneNumber = $this->phoneUtil->parse($recipient->phone, 'FR');
                    $addr->setTelephone($phoneNumber);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to parse phone number', [
                        'phone' => $recipient->phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $delivery;
    }

    private function validateForCreate(RdcApiServiceRequest $apiRequest): void
    {
        $pickupAddress = $apiRequest->getPickupAddress();
        $dropoffAddress = $apiRequest->getDropoffAddress();

        if ($pickupAddress->isEmpty() && $dropoffAddress->isEmpty()) {
            throw new \InvalidArgumentException(
                'At least one address (pickup or dropoff) is required'
            );
        }

        $hasExternalRef = !empty($apiRequest->getExternalRef());
        $hasBarcode = !empty($apiRequest->getBarcode());

        if (!$hasExternalRef && !$hasBarcode) {
            throw new \InvalidArgumentException(
                'At least one identifier (externalRef or barcode) is required'
            );
        }
    }

    private function createAddress(ServiceRequestAddress $address): Address
    {
        $streetAddress = trim(sprintf(
            '%s %s %s',
            $address->streetAddress,
            $address->addressLine1,
            $address->addressLine2
        ));

        $entityAddress = new Address();
        $entityAddress->setStreetAddress(trim($streetAddress));
        $entityAddress->setAddressLocality($address->city);
        $entityAddress->setPostalCode($address->postalCode);
        $entityAddress->setAddressCountry($address->country);

        // Geocode the address to get coordinates
        $geocoded = $this->geocoder->geocode($streetAddress);
        if ($geocoded !== null && $geocoded->getGeo() !== null) {
            $entityAddress->setGeo($geocoded->getGeo());
        }

        return $entityAddress;
    }

    private function setTaskTimeRange(Task $task, TimeSlot $timeSlot): void
    {
        // timeSlot.start = earliestDateTime (lower bound)
        // timeSlot.end = latestDateTime (upper bound)
        // Task "after" = lower bound, Task "before" = upper bound
        // The DTO holds UTC-anchored instants; convert to the instance's local TZ.
        $tz = date_default_timezone_get();

        if ($timeSlot->start !== null) {
            $task->setDoneAfter(Carbon::instance($timeSlot->start)->tz($tz)->toDateTime());
        }
        if ($timeSlot->end !== null) {
            $task->setDoneBefore(Carbon::instance($timeSlot->end)->tz($tz)->toDateTime());
        }
    }

    private function extractWeightFromLocation(?array $location): ?int
    {
        if (is_null($location)) {
            return null;
        }

        $totalKg = 0.0;
        $foundAny = false;
        foreach ($location['actions'] ?? [] as $action) {
            foreach ($action['compositionMovements'] ?? [] as $movement) {
                foreach ($movement['containedBatchesOfGoods'] ?? [] as $batch) {
                    $weight = $batch['weight'] ?? null;
                    if (!is_array($weight)) {
                        continue;
                    }
                    $unit = $weight['unit'] ?? 'KG';
                    if ($unit !== 'KG') {
                        $this->logger->warning('Unsupported weight unit, skipping', [
                            'unit' => $unit,
                        ]);
                        continue;
                    }
                    $value = $weight['value'] ?? null;
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $totalKg += (float) $value;
                    $foundAny = true;
                }
            }
        }

        return $foundAny ? (int) round($totalKg * 1000) : null;
    }

    private function isContactNotEmpty(ServiceRequestContact $contact): bool
    {
        return !empty($contact->name) || !empty($contact->phone) || !empty($contact->email);
    }

    private function buildCommentsFromSpecialInstructions(?array $specialInstructions): ?string
    {
        if (empty($specialInstructions)) {
            return null;
        }

        $emojiByCode = [
            'COMMENT' => '💬',
            'DIGICODE' => '🔢',
            'FLOOR' => '🏢',
        ];

        $lines = [];
        foreach ($specialInstructions as $instruction) {
            $code = $instruction['instructionCode'] ?? null;
            $description = $instruction['description'] ?? null;
            if (empty($code) || empty($description)) {
                continue;
            }
            $emoji = $emojiByCode[$code] ?? '📌';
            $lines[] = sprintf('%s %s: %s', $emoji, $code, $description);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    public function mapPickupAddress(RdcApiServiceRequest $apiRequest): array
    {
        $address = $apiRequest->getPickupAddress();
        return [
            'country' => $address->country ?: 'FR',
            'city' => $address->city ?: '',
            'postalCode' => $address->postalCode ?: '',
            'streetAddress' => $address->streetAddress ?: '',
            'addressLines' => array_filter([
                $address->streetAddress,
                $address->addressLine1,
                $address->addressLine2,
            ]),
        ];
    }

    public function mapDropoffAddress(RdcApiServiceRequest $apiRequest): array
    {
        $address = $apiRequest->getDropoffAddress();
        return [
            'country' => $address->country ?: 'FR',
            'city' => $address->city ?: '',
            'postalCode' => $address->postalCode ?: '',
            'streetAddress' => $address->streetAddress ?: '',
            'addressLines' => array_filter([
                $address->streetAddress,
                $address->addressLine1,
                $address->addressLine2,
            ]),
        ];
    }

    public function mapTimeSlot(TimeSlot $timeSlot): array
    {
        // The DTO holds UTC instants; the BOL outbound payload appends a hard-coded "Z" suffix
        // (see RdcServiceFacade::buildServiceLocation), so we must emit UTC-anchored DateTimes.
        return [
            'start' => $timeSlot->start
                ? Carbon::instance($timeSlot->start)->utc()->toDateTime()
                : new \DateTime(),
            'end' => $timeSlot->end
                ? Carbon::instance($timeSlot->end)->utc()->toDateTime()
                : new \DateTime(),
        ];
    }
}
