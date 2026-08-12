<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Cyke\Delivery as CykeDelivery;
use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveryCreated;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
use Spatie\OpeningHours\Exceptions\MaximumLimitExceeded;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumber;
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
        private SettingsManager $settingsManager,
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

        // EDIFACT-imported deliveries don't always carry a recipient phone number,
        // and Cyke requires one — fall back to the platform's own configured
        // phone number rather than leaving it blank.
        if (null === $telephone) {
            $fallback = $this->settingsManager->get('phone_number');
            if ($fallback instanceof PhoneNumber) {
                $telephone = $fallback;
            }
        }

        // EDIFACT-imported deliveries (see SyncTransportersCommand) carry no real
        // time information, only a date — the task's After/Before end up spanning
        // the whole day, which Cyke rejects (slot already begun/ended, or outside
        // opening hours). We send a slot that fits the store's configured Cyke
        // opening hours instead, on the dropoff's calendar date.
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
            // default package type configured for the store, with the actual
            // imported quantity (see ImportFromPoint::addPackageToTask) — or 1 if
            // the delivery carries no package data (e.g. non-EDIFACT deliveries).
            'packages' => [
                [
                    'package_type_id' => (int) $store->getCykePackageTypeId(),
                    'amount' => $dropoff->totalPackages() ?: 1,
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
     * Finds the next slot that fits the store's Cyke opening hours, so that
     * neither slot_starting_at is in the past nor slot_ending_at crosses the
     * store's closing time for that day (Cyke rejects both).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function buildCykeSlot(\DateTimeInterface $date, ?string $spec): array
    {
        $now = Carbon::now();

        // Prefer the delivery's own date, but never schedule in the past.
        $reference = Carbon::instance($date);
        if ($reference->lt($now)) {
            $reference = $now->copy();
        }

        $openingHours = self::parseOpeningHours($spec);

        if (empty($openingHours)) {
            return $this->buildDefaultSlot($reference, $now);
        }

        $registry = SpatieOpeningHoursRegistry::get($openingHours);

        try {
            // If the reference instant is already within opening hours, keep the
            // delivery on that day and clamp its end to the day's closing time;
            // otherwise jump to the next opening range. Either way slot_ending_at
            // never crosses the store's configured closing time — the reason Cyke
            // rejected Saturday slots (open until 16:00, we asked for 18:00).
            $currentRange = $registry->currentOpenRange($reference);

            if (false !== $currentRange) {
                $slotStart = $reference->copy();
                $slotEnd = $reference->copy()->setTime(
                    $currentRange->end()->hours(),
                    $currentRange->end()->minutes()
                );
            } else {
                $slotStart = Carbon::instance($registry->nextOpen($reference));
                $slotEnd = Carbon::instance($registry->nextClose($slotStart));
            }
        } catch (MaximumLimitExceeded $e) {
            return $this->buildDefaultSlot($reference, $now);
        }

        return [$slotStart, $slotEnd];
    }

    /**
     * Splits a schema.org openingHours specification (e.g.
     * "Mo-Fr 09:00-18:00, Sa 09:00-16:00") into the per-entry collection
     * expected by SchemaDotOrgParser. Commas inside a day list (e.g.
     * "Mo,We,Fr 09:00-16:00") are preserved, since each entry is matched as a
     * whole day-group + time-range pair.
     *
     * @return string[]
     */
    private static function parseOpeningHours(?string $spec): array
    {
        if (empty(trim((string) $spec))) {
            return [];
        }

        preg_match_all(
            '/((?:Mo|Tu|We|Th|Fr|Sa|Su)(?:[,\-](?:Mo|Tu|We|Th|Fr|Sa|Su))*)\s+(\d{2}:\d{2}-\d{2}:\d{2})/',
            $spec,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(
            fn(array $match): string => sprintf('%s %s', $match[1], $match[2]),
            $matches
        );
    }

    /**
     * Fallback for stores with no configured Cyke opening hours: a fixed
     * full-day slot rolled forward until it starts in the future (Cyke rejects
     * a slot that has already begun).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function buildDefaultSlot(Carbon $reference, Carbon $now): array
    {
        [$opens, $closes] = explode('-', self::DEFAULT_TIME_SLOT);

        $day = $reference->copy()->startOfDay();

        $slotStart = $day->copy()->setTimeFromTimeString($opens);
        $slotEnd = $day->copy()->setTimeFromTimeString($closes);

        while ($slotStart->lte($now)) {
            $slotStart->addDay();
            $slotEnd->addDay();
        }

        return [$slotStart, $slotEnd];
    }
}
