<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Enum\EventCode;
use AppBundle\Integration\Rdc\Enum\EventType;
use AppBundle\Message\RdcPickupStatusUpdateMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class RdcPickupStatusUpdateMessageHandler
{
    use RdcStatusUpdateHandlerTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RdcClientFactory $rdcClientFactory,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    private const CONFIG = [
        'actionType' => 'LOADING',
        'startServiceEvents' => [
            [
                'code' => EventCode::SERVICE_STARTED,
                'type' => EventType::SCHEDULE,
                'description' => 'Service livraison %s démarrée',
            ],
            [
                'code' => EventCode::DEPARTURE,
                'type' => EventType::TRANSPORT,
                'description' => 'Service livraison %s démarré',
            ],
        ],
        'startActivityEvents' => [
            [
                'code' => EventCode::ACTIVITY_STARTED,
                'type' => EventType::SCHEDULE,
                'description' => 'Activité %s démarrée',
            ],
            [
                'code' => EventCode::DEPARTURE,
                'type' => EventType::TRANSPORT,
                'description' => 'Activité %s démarrée',
            ],
        ],
        'serviceEvents' => [
            [
                'code' => EventCode::DEPARTURE,
                'type' => EventType::TRANSPORT,
                'description' => 'Service livraison %s démarré',
            ],
        ],
        'activityEvents' => [
            [
                'code' => EventCode::DEPARTURE,
                'type' => EventType::TRANSPORT,
                'description' => 'Activité %s démarrée',
            ],
        ],
    ];

    public function __invoke(RdcPickupStatusUpdateMessage $message): void
    {
        $this->processStatusUpdate($message, self::CONFIG);
    }
}