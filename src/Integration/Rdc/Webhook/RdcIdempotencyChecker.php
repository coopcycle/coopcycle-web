<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Webhook;

use AppBundle\Entity\Rdc\RdcProcessedWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RdcIdempotencyChecker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isAlreadyProcessed(string $loUri): bool
    {
        $existing = $this->entityManager
            ->getRepository(RdcProcessedWebhook::class)
            ->findOneBy(['loUri' => $loUri]);

        return $existing !== null;
    }

    public function markAsProcessed(string $loUri, string $eventType): void
    {
        $webhook = new RdcProcessedWebhook($loUri, $eventType);
        $this->entityManager->persist($webhook);
        $this->entityManager->flush();

        $this->logger->info('RDC webhook marked as processed', [
            'lo_uri' => $loUri,
            'event_type' => $eventType,
        ]);
    }

    public function resolveEventType(string $loUri, string $eventType): string
    {
        $existing = $this->entityManager
            ->getRepository(RdcProcessedWebhook::class)
            ->findOneBy(['loUri' => $loUri]);

        if ($existing === null) {
            return $eventType;
        }

        if ($eventType === 'create' && $existing->getEventType() === 'create') {
            $this->logger->info('RDC webhook resolveEventType: create on existing entity -> update', [
                'lo_uri' => $loUri,
            ]);
            return 'update';
        }

        return $eventType;
    }
}
