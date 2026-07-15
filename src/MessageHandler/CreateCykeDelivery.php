<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Cyke\Delivery as CykeDelivery;
use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveryCreated;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class CreateCykeDelivery
{
    private const DEFAULT_TIME_SLOT = '08:00-18:00';

    private $logger;

    public function __construct(
        private HttpClientInterface $cykeClient,
        private EntityManagerInterface $entityManager,
        private PhoneNumberUtil $phoneNumberUtil,
        private bool $cykeEnabled = false,
        ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(DeliveryCreated $message)
    {
        if (!$this->cykeEnabled) {
            return;
        }

        $delivery = $this->entityManager->getRepository(Delivery::class)->find($message->getDeliveryId());

        if (!$delivery) {
            return;
        }

        $store = $delivery->getStore();

        if (null === $store || !$store->isCykeEnabled()) {
            return;
        }

        $existing = $this->entityManager
            ->getRepository(CykeDelivery::class)
            ->findOneBy(['delivery' => $delivery]);

        if (null !== $existing) {
            return;
        }

        $dropoff = $delivery->getDropoff();
        $address = $dropoff->getAddress();

        $telephone = $address->getTelephone();

        // EDIFACT-imported deliveries (see SyncTransportersCommand) carry no real
        // time information, only a date — the task's After/Before end up spanning
        // the whole day, which Cyke rejects (slot already begun/ended, or outside
        // opening hours). We send the store's configured Cyke slot instead, on the
        // dropoff's calendar date.
        [$slotStart, $slotEnd] = $this->buildCykeSlot($dropoff->getAfter(), $store->getCykeTimeSlot());

        $payload = [
            'dropoff' => [
                'slot_starting_at' => $slotStart->toIso8601String(),
                'slot_ending_at' => $slotEnd->toIso8601String(),
                'place' => [
                    'recipient_name' => $address->getContactName() ?: $address->getName(),
                    'recipient_phone' => $telephone ? $this->phoneNumberUtil->format($telephone, PhoneNumberFormat::E164) : null,
                    'company_name' => $address->getCompany(),
                    'address' => $address->getStreetAddress(),
                    'postal_code' => $address->getPostalCode(),
                    'city' => $address->getAddressLocality(),
                    'address_instructions' => $address->getDescription(),
                ],
            ],
            // CoopCycle doesn't track per-delivery package details for every store,
            // and Cyke requires at least one package, so we always send the single
            // default package type configured for the store, with a quantity of 1.
            'packages' => [
                [
                    'package_type_id' => (int) $store->getCykePackageTypeId(),
                    'amount' => 1,
                ],
            ],
            'comments' => $dropoff->getComments(),
            'client_order_reference' => (string) $delivery->getId(),
        ];

        $this->logger->info(
            sprintf('Sending delivery #%d to Cyke', $delivery->getId()),
            ['payload' => $payload]
        );

        try {
            $response = $this->cykeClient->request('POST', 'deliveries', [
                'headers' => [
                    'X-User-Email' => $store->getCykeUserEmail(),
                    'X-User-Token' => $store->getCykeUserToken(),
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            $this->logger->info(
                sprintf('Cyke accepted delivery #%d', $delivery->getId()),
                ['response' => $data]
            );

            $cykeDelivery = new CykeDelivery();
            $cykeDelivery->setDelivery($delivery);
            $cykeDelivery->setCykeId((string) $data['id']);

            $this->entityManager->persist($cykeDelivery);
            $this->entityManager->flush();

        } catch (HttpExceptionInterface $e) {
            $this->logger->error(
                sprintf('Cyke rejected delivery #%d: %s', $delivery->getId(), $e->getMessage()),
                [
                    'payload' => $payload,
                    'response' => $e->getResponse()->getContent(false),
                ]
            );
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(
                sprintf('Cyke request failed for delivery #%d: %s', $delivery->getId(), $e->getMessage()),
                ['payload' => $payload]
            );
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function buildCykeSlot(\DateTimeInterface $date, ?string $timeSlot): array
    {
        [$opens, $closes] = explode('-', $timeSlot ?: self::DEFAULT_TIME_SLOT);

        $day = Carbon::instance($date)->startOfDay();

        $slotStart = $day->copy()->setTimeFromTimeString($opens);
        $slotEnd = $day->copy()->setTimeFromTimeString($closes);

        // SyncTransportersCommand always schedules EDIFACT imports for "today"
        // (see importScontrTask/importPickupTask), so by the time this message is
        // processed the configured slot may have already started, or even ended.
        // Cyke rejects a slot that has already begun, so roll it forward a day at
        // a time until it's actually in the future.
        $now = Carbon::now();
        while ($slotStart->lte($now)) {
            $slotStart->addDay();
            $slotEnd->addDay();
        }

        return [$slotStart, $slotEnd];
    }
}
