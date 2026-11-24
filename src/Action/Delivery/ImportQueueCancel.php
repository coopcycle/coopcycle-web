<?php

declare(strict_types=1);

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery\ImportQueue;
use AppBundle\Service\DeliveryImportStatusManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A pending import is skipped once a worker picks it up. One being read is
 * stopped by the worker the next time it reports its progress. Once the worker
 * has started saving the deliveries, it is too late.
 */
class ImportQueueCancel
{
    public function __construct(
        private DeliveryImportStatusManager $statusManager,
        private LoggerInterface $logger,
    )
    {}

    public function __invoke(ImportQueue $data): ImportQueue
    {
        if (!$data->isCancellable()) {
            throw new BadRequestHttpException(sprintf('The import is %s, it can not be cancelled', $data->getStatus()));
        }

        // The worker may have moved on to saving the deliveries since the import was loaded
        if (!$this->statusManager->cancel($data)) {
            throw new BadRequestHttpException(sprintf('The import is %s, it can not be cancelled', $this->statusManager->currentStatus($data)));
        }

        $this->logger->info(
            sprintf('Delivery import %s was cancelled', $data->getFilename()),
            ['store_id' => $data->getStore()->getId()]
        );

        return $data;
    }
}
