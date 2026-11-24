<?php

declare(strict_types=1);

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery\ImportQueue;
use AppBundle\Message\CancelImportedDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A large file is more than a thousand deliveries, each cancelled with its order:
 * longer than a request lasts, they are cancelled by a worker.
 */
class ImportQueueCancelDeliveries
{
    public function __construct(
        private DeliveryImportStatusManager $statusManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    )
    {}

    public function __invoke(ImportQueue $data): ImportQueue
    {
        if (!$data->canCancelDeliveries()) {
            throw new BadRequestHttpException(sprintf('The deliveries of an import that is %s can not be cancelled', $this->describe($data)));
        }

        // Another dispatcher may have asked in the meantime
        if (!$this->statusManager->cancellingDeliveries($data)) {
            throw new BadRequestHttpException(sprintf('The deliveries of %s are already being cancelled', $data->getFilename()));
        }

        $this->messageBus->dispatch(new CancelImportedDeliveries($data->getId()));

        $this->logger->info(
            sprintf('Cancelling the deliveries of import %s', $data->getFilename()),
            ['store_id' => $data->getStore()->getId()]
        );

        return $data;
    }

    private function describe(ImportQueue $data): string
    {
        if (!is_null($data->getDeliveriesCancellation())) {
            return sprintf('%s, with its deliveries %s', $data->getStatus(), $data->getDeliveriesCancellation());
        }

        return $data->getStatus();
    }
}
