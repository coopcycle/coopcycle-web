<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Enum\EventCode;
use AppBundle\Integration\Rdc\Enum\EventType;
use AppBundle\Message\RdcDropoffStatusUpdateMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RdcDropoffStatusUpdateMessageHandler
{
    use RdcStatusUpdateHandlerTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RdcClientFactory $rdcClientFactory,
        private readonly LoggerInterface $logger,
    ) {}

    private const CONFIG = [
        'actionType' => 'UNLOADING',
        'shouldPatch' => true,
        'serviceEvents' => [
            [
                'code' => EventCode::SERVICE_FINISHED,
                'type' => EventType::SCHEDULE,
                'description' => 'Service livraison %s terminé',
            ],
            [
                'code' => EventCode::DELIVERY,
                'type' => EventType::TRANSPORT,
                'description' => 'Service livraison %s terminé',
            ],
        ],
        'activityEvents' => [
            [
                'code' => EventCode::ACTIVITY_FINISHED,
                'type' => EventType::SCHEDULE,
                'description' => 'Activité %s terminée',
            ],
            [
                'code' => EventCode::DELIVERY,
                'type' => EventType::TRANSPORT,
                'description' => 'Activité %s terminée',
            ],
        ],
    ];

    public function __invoke(RdcDropoffStatusUpdateMessage $message): void
    {
        $this->processStatusUpdate($message, self::CONFIG);
    }
}