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

        // RDC provenance is delivery-level: keep it on the delivery metadata bag,
        // nested under the `rdc` namespace (see Delivery::$metadata).
        $delivery->setMetadata('rdc.lo_uri', $loUri);
        $delivery->setMetadata('rdc.created_at', (new DateTime())->format(DateTimeInterface::ATOM));

        // The requestor label number (REQUESTOR_LABEL_ID) is the one printed on
        // the parcel, so it is what dispatch and riders look for.
        $externalRef = $apiRequest->getBarcode();
        if (!is_null($externalRef)) {
            $delivery->setExternalReference($externalRef);
        }

        $requestorId = $apiRequest->getExternalRef();
        if (!is_null($requestorId)) {
            $delivery->setMetadata('rdc.requestor_id', $requestorId);
        }

        $weightGrams = $this->extractWeightFromLocation($apiRequest->startLocation);
        if (!is_null($weightGrams)) {
            $pickup->setWeight($weightGrams);
        }

        // The label number is printed on the parcel: riders look for it.
        $reference = is_null($externalRef) ? null : sprintf('🏷️ Reference: %s', $externalRef);
        // The special characteristics describe the goods: both tasks need them.
        $characteristics = $this->buildCommentsFromSpecialCharacteristics($apiRequest->specialCharacteristics);

        $pickupComments = $this->joinComments([
            $reference,
            $characteristics,
            $this->buildCommentsFromSpecialInstructions($apiRequest->getPickupSpecialInstructions()),
        ]);
        if (!is_null($pickupComments)) {
            $pickup->setComments($pickupComments);
        }

        // Instructions on the service request itself apply to the dropoff
        $dropoffComments = $this->joinComments([
            $reference,
            $characteristics,
            $this->buildCommentsFromSpecialInstructions($apiRequest->specialInstructions),
            $this->buildCommentsFromSpecialInstructions($apiRequest->getDropoffSpecialInstructions()),
        ]);
        if (!is_null($dropoffComments)) {
            $dropoff->setComments($dropoffComments);
        }

        $this->applyContact($pickup->getAddress(), $apiRequest->getShipperContact());
        // Applicolis sends no name on the RECIPIENT contact, the recipient name
        // is the dropoff location name.
        $this->applyContact($dropoff->getAddress(), $apiRequest->getRecipientContact(), $dropoffAddress->name);

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
        // Some providers put the city in the address line, others send it
        // apart: the geocoder needs it either way.
        $streetAddress = $address->streetAddress;
        $locality = trim(sprintf('%s %s', $address->postalCode, $address->city));
        $hasLocality = str_contains($streetAddress, $address->postalCode ?: $address->city);
        if (!empty($locality) && !$hasLocality) {
            $streetAddress = sprintf('%s, %s', $streetAddress, $locality);
        }

        $entityAddress = new Address();
        $entityAddress->setStreetAddress($streetAddress);
        $entityAddress->setAddressLocality($address->city);
        $entityAddress->setPostalCode($address->postalCode);
        $entityAddress->setAddressCountry($address->country);
        if (!empty($address->name)) {
            $entityAddress->setName($address->name);
        }

        // The other lines are complements ("Bâtiment B"), which would only
        // confuse the geocoder.
        $complement = trim(sprintf('%s %s', $address->addressLine1, $address->addressLine2));
        if (!empty($complement)) {
            $entityAddress->setDescription($complement);
        }

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
            if (empty($description)) {
                continue;
            }
            // Without a code, it is a free comment
            if (empty($code)) {
                $lines[] = sprintf('%s %s', $emojiByCode['COMMENT'], $description);
                continue;
            }
            $emoji = $emojiByCode[$code] ?? '📌';
            $lines[] = sprintf('%s %s: %s', $emoji, $code, $description);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    private function buildCommentsFromSpecialCharacteristics(?array $specialCharacteristics): ?string
    {
        if (empty($specialCharacteristics)) {
            return null;
        }

        $emojiByReference = [
            'CUSTOMER_ORDER_DESCRIPTION' => '📦',
        ];

        $lines = [];
        foreach ($specialCharacteristics as $characteristic) {
            $reference = $characteristic['characteristicReference'] ?? null;
            $label = $characteristic['specialCharacteristicType'] ?? $reference;

            $value = $characteristic['stringValue'] ?? null;
            $numericalValue = $characteristic['numericalValue'] ?? null;
            if (empty($value) && isset($numericalValue['value'])) {
                $value = trim(sprintf('%s %s', $numericalValue['value'], $numericalValue['unit'] ?? ''));
            }
            if (empty($value)) {
                continue;
            }

            $emoji = $emojiByReference[$reference ?? ''] ?? '⭐';
            $lines[] = empty($label)
                ? sprintf('%s %s', $emoji, $value)
                : sprintf('%s %s: %s', $emoji, $label, $value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    private function joinComments(array $parts): ?string
    {
        $parts = array_filter($parts);

        return empty($parts) ? null : implode("\n", $parts);
    }

    private function applyContact(?Address $address, ServiceRequestContact $contact, string $fallbackName = ''): void
    {
        if (is_null($address)) {
            return;
        }

        $name = $contact->name ?: $fallbackName;
        if (!empty($name)) {
            $address->setContactName($name);
        }

        if (empty($contact->phone)) {
            return;
        }

        try {
            $address->setTelephone($this->phoneUtil->parse($contact->phone, 'FR'));
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse phone number', [
                'phone' => $contact->phone,
                'error' => $e->getMessage(),
            ]);
        }
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
