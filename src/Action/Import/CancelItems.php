<?php

declare(strict_types=1);

namespace AppBundle\Action\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Message\CancelImportedItems;
use AppBundle\Service\ImportStatusManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A large file is more than a thousand deliveries or tasks, a delivery cancelled with
 * its order: longer than a request lasts, they are cancelled by a worker.
 */
class CancelItems
{
    public function __construct(
        private ImportStatusManager $statusManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    )
    {}

    public function __invoke(ImportQueue $data): ImportQueue
    {
        if (!$data->canCancelItems()) {
            throw new BadRequestHttpException(sprintf('What an import that is %s created can not be cancelled', $this->describe($data)));
        }

        // Another dispatcher may have asked in the meantime
        if (!$this->statusManager->cancellingItems($data)) {
            throw new BadRequestHttpException(sprintf('What %s created is already being cancelled', $data->getFilename()));
        }

        $this->messageBus->dispatch(new CancelImportedItems($data->getId()));

        $this->logger->info(
            sprintf('Cancelling what import %s created', $data->getFilename()),
            ['import_id' => $data->getId(), 'import_type' => $data->getType()]
        );

        return $data;
    }

    private function describe(ImportQueue $data): string
    {
        if (!is_null($data->getItemsCancellation())) {
            return sprintf('%s, with what it created %s', $data->getStatus(), $data->getItemsCancellation());
        }

        return $data->getStatus();
    }
}
