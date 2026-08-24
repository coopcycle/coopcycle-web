<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Integration\Rdc\Enum\ActionState;
use AppBundle\Integration\Rdc\Enum\EventCode;
use AppBundle\Integration\Rdc\Enum\EventDomain;
use AppBundle\Integration\Rdc\Enum\EventType;
use AppBundle\Integration\Rdc\RdcEventDispatcher;
use AppBundle\Integration\Rdc\RdcTaskContextResolver;
use AppBundle\Message\IncidentRdcMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class IncidentRdcMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RdcTaskContextResolver $rdcTaskContextResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RdcEventDispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly bool $rdcEnabled = false,
    ) {
    }

    public function __invoke(IncidentRdcMessage $message): void
    {
        if (!$this->rdcEnabled) {
            return;
        }

        $incident = $this->entityManager->find(Incident::class, $message->incidentId);
        if (is_null($incident)) {
            $this->logger->warning('Incident not found, skipping RDC dispatch', [
                'incident_id' => $message->incidentId,
            ]);
            return;
        }

        if ($incident->getFailureReasonCode() === 'price_review_needed') {
            $this->logger->info('Skipping RDC incident dispatch: price_review_needed', [
                'incident_id' => $incident->getId(),
            ]);
            return;
        }

        $task = $incident->getTask();
        $context = $this->rdcTaskContextResolver->resolve($task);
        if (is_null($context)) {
            $this->logger->info('Skipping RDC incident dispatch: no RDC context available', [
                'incident_id' => $incident->getId(),
                'task_id' => $task->getId(),
            ]);
            return;
        }

        $code = $task->isPickup() ? EventCode::NOT_COLLECTED : EventCode::NOT_DELIVERED;
        $documents = $this->buildDocumentsFromTask($task);
        $actualDateTime = $this->toDateTimeImmutable($incident->getCreatedAt());
        $now = new \DateTimeImmutable();

        $payload = [
            'eventType' => EventType::TRANSPORT->value,
            'code' => $code->value,
            'domain' => EventDomain::ISSUE->value,
            'description' => sprintf('Incident: %s', $incident->getTitle() ?? ''),
            'location' => $this->buildLocationFromTask($task, $actualDateTime),
            'actualDateTime' => $actualDateTime->format(\DateTimeInterface::ATOM),
            'creationDateTime' => $now->format(\DateTimeInterface::ATOM),
        ];

        $this->eventDispatcher->dispatch($context->client, 'services', $context->serviceId, $payload, $documents);
        $this->eventDispatcher->dispatch($context->client, 'activities', $context->activityId, $payload, $documents);

        $this->logger->info('RDC incident event dispatched', [
            'incident_id' => $incident->getId(),
            'task_id' => $task->getId(),
            'code' => $code->value,
            'documents_count' => count($documents),
        ]);
    }

    private function buildDocumentsFromTask(Task $task): array
    {
        $documents = [];
        $subtype = $task->isPickup() ? 'DELIVERY_ADDITIONAL_EVIDENCE' : 'PROOF_OF_DELIVERY';
        foreach ($task->getImages() as $image) {
            $documents[] = $this->buildDocumentFromImage($image, $subtype);
        }
        return $documents;
    }

    private function buildDocumentFromImage(TaskImage $image, string $subtype): array
    {
        return [
            'cmsUri' => $this->urlGenerator->generate(
                'task_image_public',
                ['path' => $image->getImageName()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'documentName' => 'Photo de preuve de livraison',
            'documentType' => 'TRANSPORT',
            'documentSubtype' => $subtype,
        ];
    }

    private function buildLocationFromTask(Task $task, \DateTimeImmutable $actionTime): array
    {
        $address = $task->getAddress();

        return [
            'address' => [
                'addressCountry' => ['countryCode' => 'FR', 'countryName' => 'France'],
                'addressLocality' => $address?->getAddressLocality(),
                'postalCode' => $address?->getPostalCode(),
                'addressLines' => [$address?->getStreetAddress()],
            ],
            'locationName' => '',
            'actualStartDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'actualEndDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'action' => [
                'actionName' => 'Incident',
                'actionState' => ActionState::ACTUAL->value,
                'actionType' => 'INCIDENT',
                'actionSubtype' => '',
                'sequenceNumber' => 1,
            ],
        ];
    }

    private function toDateTimeImmutable(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        return new \DateTimeImmutable();
    }
}