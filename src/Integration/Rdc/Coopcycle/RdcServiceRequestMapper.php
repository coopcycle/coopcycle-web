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
        Store $store
    ): Delivery {
        $this->validateForCreate($apiRequest);
        $loUri = $apiRequest->getUri();

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
            'rdc_barcode' => $apiRequest->getBarcode(),
            'rdc_contract_ref' => $apiRequest->getContractRef(),
            'rdc_created_at' => (new DateTime())->format(DateTimeInterface::ATOM),
        ]);

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

        $hasExternalRef = $apiRequest->getExternalRef() !== null;
        $hasBarcode = $apiRequest->getBarcode() !== null;

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
        if ($timeSlot->start !== null) {
            $task->setDoneAfter(\DateTime::createFromImmutable($timeSlot->start));
        }
        if ($timeSlot->end !== null) {
            $task->setDoneBefore(\DateTime::createFromImmutable($timeSlot->end));
        }
    }

    private function isContactNotEmpty(ServiceRequestContact $contact): bool
    {
        return $contact->name !== '' || $contact->phone !== '' || $contact->email !== '';
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
        return [
            'start' => $timeSlot->start
                ? \DateTime::createFromImmutable($timeSlot->start)
                : new \DateTime(),
            'end' => $timeSlot->end
                ? \DateTime::createFromImmutable($timeSlot->end)
                : new \DateTime(),
        ];
    }
}
