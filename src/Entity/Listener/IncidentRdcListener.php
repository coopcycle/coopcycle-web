<?php

declare(strict_types=1);

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Message\IncidentRdcMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsEntityListener(event: Events::postPersist, entity: Incident::class)]
final class IncidentRdcListener
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function postPersist(Incident $incident, PostPersistEventArgs $args): void
    {
        $incidentId = $incident->getId();
        if (is_null($incidentId)) {
            return;
        }

        $this->messageBus->dispatch(
            new IncidentRdcMessage($incidentId),
            [new DelayStamp(45000)]
        );
    }
}