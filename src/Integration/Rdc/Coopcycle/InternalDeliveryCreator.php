<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\DTO\ServiceRequest;
use AppBundle\Integration\Rdc\DTO\ServiceRequestAddress;
use AppBundle\Integration\Rdc\DTO\ServiceRequestContact;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Service\DeliveryOrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InternalDeliveryCreator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryOrderManager $deliveryOrderManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Creates a Delivery entity with tasks from the RDC order data.
     */
    public function createDelivery(ServiceRequest $orderData, Store $store, string $loUri): Delivery
    {
        $this->logger->info('Creating Coopcycle delivery from RDC order', [
            'externalRef' => $orderData->externalRef,
            'barcode' => $orderData->barcode,
            'lo_uri' => $loUri,
        ]);

        $delivery = new Delivery();
        $delivery->setStore($store);

        $pickupAddress = $orderData->addresses['pickup'] ?? new ServiceRequestAddress();
        if (!$pickupAddress->isEmpty()) {
            $pickup = $delivery->getPickup();
            $pickup->setAddress($this->createAddress($pickupAddress));
            $this->setTaskTimeRange($pickup, $orderData->timeSlots['pickup'] ?? new TimeSlot());
        }

        $dropoffAddress = $orderData->addresses['dropoff'] ?? new ServiceRequestAddress();
        if (!$dropoffAddress->isEmpty()) {
            $dropoff = $delivery->getDropoff();
            $dropoff->setAddress($this->createAddress($dropoffAddress));
            $this->setTaskTimeRange($dropoff, $orderData->timeSlots['dropoff'] ?? new TimeSlot());
        }

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $this->setDefaultTimeWindows($pickup, $dropoff);

        $pickup->setMetadata([
            'rdc_lo_uri' => $loUri,
            'rdc_external_ref' => $orderData->externalRef,
            'rdc_barcode' => $orderData->barcode,
            'rdc_contract_ref' => $orderData->contractRef,
            'rdc_created_at' => (new \DateTime())->format(\DateTimeInterface::ATOM),
        ]);

        $recipient = $orderData->contacts['RECIPIENT'] ?? null;
        if ($recipient instanceof ServiceRequestContact) {
            $recipientInfo = sprintf(
                'Recipient: %s, Phone: %s, Email: %s',
                $recipient->name ?: 'N/A',
                $recipient->phone ?: 'N/A',
                $recipient->email ?: 'N/A'
            );
            $dropoff->setComments($recipientInfo);
        }

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        $this->logger->info('Coopcycle delivery created successfully', [
            'delivery_id' => $delivery->getId(),
            'lo_uri' => $loUri,
        ]);

        return $delivery;
    }

    public function createDeliveryWithOrder(ServiceRequest $orderData, Store $store, string $loUri): Delivery
    {
        $delivery = $this->createDelivery($orderData, $store, $loUri);

        $this->deliveryOrderManager->createOrder($delivery, [
            'pricingStrategy' => new CalculateUsingPricingRules(),
            'persist' => false,
        ]);

        $this->entityManager->flush();

        $this->logger->info('Coopcycle delivery and order created successfully', [
            'delivery_id' => $delivery->getId(),
            'order_number' => $delivery->getOrder()?->getNumber(),
            'lo_uri' => $loUri,
        ]);

        return $delivery;
    }

    private function createAddress(ServiceRequestAddress $address): Address
    {
        $entityAddress = new Address();

        $streetAddress = trim(
            $address->streetAddress . ' ' .
            $address->addressLine1 . ' ' .
            $address->addressLine2
        );

        $entityAddress->setStreetAddress(trim($streetAddress));
        $entityAddress->setAddressLocality($address->city);
        $entityAddress->setPostalCode($address->postalCode);
        $entityAddress->setAddressCountry($address->country);

        return $entityAddress;
    }

    private function setTaskTimeRange(Task $task, TimeSlot $timeSlot): void
    {
        if (!$timeSlot->isEmpty()) {
            if ($timeSlot->start instanceof \DateTimeImmutable) {
                $task->setDoneAfter(
                    \DateTime::createFromImmutable($timeSlot->start->modify('-30 minutes'))
                );
            }

            if ($timeSlot->end instanceof \DateTimeImmutable) {
                $task->setDoneBefore(
                    \DateTime::createFromImmutable($timeSlot->end->modify('+30 minutes'))
                );
            }
        }
    }

    private function setDefaultTimeWindows(Task $pickup, Task $dropoff): void
    {
        $now = new \DateTime();
        $endOfDay = (clone $now)->modify('+8 hours');

        if ($pickup->getDoneBefore() === null) {
            $pickup->setDoneBefore($endOfDay);
        }
        if ($pickup->getDoneAfter() === null) {
            $pickup->setDoneAfter(clone $now);
        }

        if ($dropoff->getDoneBefore() === null) {
            $dropoff->setDoneBefore($endOfDay);
        }
        if ($dropoff->getDoneAfter() === null) {
            $dropoff->setDoneAfter(clone $now);
        }
    }
}