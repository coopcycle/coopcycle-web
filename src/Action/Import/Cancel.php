<?php

declare(strict_types=1);

namespace AppBundle\Action\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Service\ImportStatusManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A pending import is skipped once a worker picks it up. One being read is
 * stopped by the worker the next time it reports its progress. Once the worker
 * has started saving what it read, it is too late.
 */
class Cancel
{
    public function __construct(
        private ImportStatusManager $statusManager,
        private LoggerInterface $logger,
    )
    {}

    public function __invoke(ImportQueue $data): ImportQueue
    {
        if (!$data->isCancellable()) {
            throw new BadRequestHttpException(sprintf('The import is %s, it can not be cancelled', $data->getStatus()));
        }

        // The worker may have moved on to saving since the import was loaded
        if (!$this->statusManager->cancel($data)) {
            throw new BadRequestHttpException(sprintf('The import is %s, it can not be cancelled', $this->statusManager->currentStatus($data)));
        }

        $this->logger->info(
            sprintf('Import %s was cancelled', $data->getFilename()),
            ['import_id' => $data->getId(), 'import_type' => $data->getType()]
        );

        return $data;
    }
}
