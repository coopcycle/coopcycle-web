<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use League\Flysystem\Filesystem;

/**
 * Everything that differs between one kind of import and another.
 *
 * Reading the file, reporting the progress, stopping a cancelled import, the
 * transaction and what to do when it fails are the same whatever the file holds:
 * they live in RunImportHandler, which asks an implementation of this for the rest.
 */
interface ImportHandlerInterface
{
    const ERROR_INTERRUPTED = 'interrupted';
    const ERROR_STALLED = 'stalled';
    const ERROR_ROLLED_BACK = 'rolled_back';

    /**
     * The ImportQueue::TYPE_* this handles
     */
    public function getType(): string;

    /**
     * How the validator constraints name this kind of file, as in
     * new Spreadsheet('delivery'), which is not how a queue row names it
     */
    public function getParserType(): string;

    public function getFilesystem(): Filesystem;

    public function getParser(): AbstractSpreadsheetParser;

    /**
     * The options to read the file with, under those the message carries
     */
    public function defaultOptions(ImportQueue $queue): array;

    /**
     * Called once the file is read, before the transaction is opened
     */
    public function beforeSave(ImportQueue $queue): void;

    /**
     * Saves what the file held, inside a transaction. Rows that fail validation are
     * reported on the result and skipped, rather than failing the whole file.
     *
     * @param ?int $rowNumber set to the row being saved, for the error to point at it
     *                        when saving fails
     *
     * @return string[] the days the tasks saved are on, as Y-m-d, for the boards
     *                  showing one of them to reload
     */
    public function save(ImportQueue $queue, SpreadsheetParseResult $result, ?int &$rowNumber): array;

    /**
     * Called once the transaction is committed
     */
    public function afterSave(ImportQueue $queue): void;

    /**
     * Called once it is rolled back
     */
    public function onSaveFailed(ImportQueue $queue): void;

    /**
     * What to tell a dispatcher about something that happened to the import as a whole,
     * rather than to one of its rows.
     *
     * @param string $kind one of the ERROR_* constants
     *
     * @return string a translation key
     */
    public function errorKey(string $kind): string;
}
