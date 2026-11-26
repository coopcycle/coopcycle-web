<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Exception\ImportRolledBackException;
use AppBundle\Import\ImportRunner;
use AppBundle\Message\RunsImport;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Runs an import a worker was asked for, whatever kind of file it is.
 *
 * The import itself is ImportRunner, which the commands that wait for its result call
 * directly. This only turns a rolled back import into an unrecoverable message.
 */
#[AsMessageHandler]
class RunImportHandler
{
    public function __construct(
        private readonly ImportRunner $importRunner,
    )
    {
    }

    public function __invoke(RunsImport $message): void
    {
        try {
            $this->importRunner->run($message->getFilename(), $message->getOptions(), $message->getQueueId());
        } catch (ImportRolledBackException $e) {
            // Nothing was imported, and a retry would most likely fail the same way:
            // the file can be imported again as a whole
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }
}
