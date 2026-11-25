<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\ApiResource;
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Action\Import\Csv as CsvController;
use AppBundle\Action\Import\Redownload as RedownloadController;
use AppBundle\Action\Import\Cancel as CancelController;
use AppBundle\Action\Import\CancelItems as CancelItemsController;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One uploaded spreadsheet, from the moment it lands to the moment what it holds is saved.
 *
 * The kind of thing a file creates is the only difference between one import and another:
 * everything here — the statuses, the column mapping, the progress, the errors, cancelling
 * the import and cancelling what it created — is the same for all of them.
 *
 * @see \AppBundle\Import\ImportHandlerInterface for what each kind does with the rows
 */
#[ApiResource(
    shortName: 'ImportQueue',
    operations: [
        new Get(normalizationContext: ['groups' => ['import_queue']],
            security: 'is_granted(\'view\', object)'),
        new Get(
            uriTemplate: '/import_queues/{id}/csv',
            controller: CsvController::class,
            security: 'is_granted(\'view\', object)'
        ),
        new Get(
            uriTemplate: '/import_queues/{id}/redownload',
            controller: RedownloadController::class,
            security: 'is_granted(\'view\', object)'
        ),
        new Put(
            uriTemplate: '/import_queues/{id}/cancel',
            controller: CancelController::class,
            openapiContext: ['summary' => 'Cancels an import that is pending or in progress'],
            security: 'is_granted(\'edit\', object)',
            // The status is written by the controller
            write: false,
            deserialize: false
        ),
        new Put(
            uriTemplate: '/import_queues/{id}/cancel_items',
            controller: CancelItemsController::class,
            openapiContext: ['summary' => 'Cancels what an import created, but what is already under way'],
            security: 'is_granted(\'edit\', object)',
            // Written by the controller, the items are cancelled by a worker
            write: false,
            deserialize: false
        )
    ],
    normalizationContext: ['groups' => ['import_queue']]
)]
class ImportQueue implements OrganizationAwareInterface
{
    use Timestampable;
    use OrganizationAwareTrait;

    const TYPE_DELIVERIES = 'deliveries';
    const TYPE_TASKS = 'tasks';

    // Uploaded, waiting for a dispatcher to say which field each of its columns holds.
    // A worker never picks one up: the handler only imports a pending import.
    const STATUS_MAPPING = 'mapping';
    const STATUS_PENDING = 'pending';
    const STATUS_STARTED = 'started';
    // The file is read, what it holds is being saved: too late to cancel
    const STATUS_SAVING = 'saving';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The statuses a dispatcher can cancel an import in: it has not saved anything yet.
     *
     * @see self::isCancellable()
     * @see \AppBundle\Service\ImportStatusManager::cancel()
     */
    const CANCELLABLE_STATUSES = [
        self::STATUS_MAPPING,
        self::STATUS_PENDING,
        self::STATUS_STARTED,
    ];

    // Cancelling what an import created, once it's done
    const ITEMS_CANCELLING = 'cancelling';
    const ITEMS_CANCELLED = 'cancelled';

    protected $id;

    /**
     * What the rows of the file are: deliveries or tasks. Decides which parser reads it,
     * which filesystem holds it and what is made of its rows.
     */
    #[Groups(['import_queue'])]
    protected string $type;

    protected string $filename;

    /**
     * The store a delivery import belongs to. Null for the imports that have none,
     * a task import for instance.
     */
    protected ?Store $store = null;

    #[Assert\Type(type: 'string')]
    #[Groups(['import_queue'])]
    protected $status = self::STATUS_PENDING;

    protected $startedAt;

    protected $finishedAt;

    #[Groups(['import_queue'])]
    protected array $errors = [];

    /**
     * Which field each column of the file holds, as picked on the mapping screen,
     * by column index. Null for an import that is waiting for one, and for the
     * imports that predate the mapping screen and the files sent to the API,
     * which are read by their header names.
     *
     * @var ?array<int,string>
     */
    protected ?array $columnMapping = null;

    /**
     * What the dispatcher chose when uploading the file, handed to the parser by the
     * worker: the date a task import defaults its rows to, for instance. The options
     * carried by the message itself win over these.
     */
    protected ?array $options = null;

    /**
     * Unknown until a worker has read the file
     */
    #[Groups(['import_queue'])]
    protected ?int $totalRows = null;

    #[Groups(['import_queue'])]
    protected int $processedRows = 0;

    #[Groups(['import_queue'])]
    protected int $errorRows = 0;

    /**
     * When the worker last reported its progress, to tell a long import from a dead one
     */
    protected ?\DateTime $progressedAt = null;

    /**
     * Null until a dispatcher cancels what the import created
     */
    #[Groups(['import_queue'])]
    protected ?string $itemsCancellation = null;

    /**
     * Unknown until a worker has looked them up
     */
    #[Groups(['import_queue'])]
    protected ?int $itemsToCancel = null;

    #[Groups(['import_queue'])]
    protected int $cancelledItems = 0;

    /**
     * Already under way, or that can't be cancelled any more
     */
    #[Groups(['import_queue'])]
    protected int $skippedItems = 0;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isDeliveries(): bool
    {
        return self::TYPE_DELIVERIES === $this->type;
    }

    public function isTasks(): bool
    {
        return self::TYPE_TASKS === $this->type;
    }

    public function setStore(?Store $store)
    {
        $this->store = $store;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;
    }

    public function setFinishedAt(\DateTime $finishedAt)
    {
        $this->finishedAt = $finishedAt;
    }

    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return ?array<int,string>
     */
    public function getColumnMapping(): ?array
    {
        return $this->columnMapping;
    }

    /**
     * @param ?array<int,string> $columnMapping
     */
    public function setColumnMapping(?array $columnMapping): void
    {
        $this->columnMapping = $columnMapping;
    }

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }

    public function isWaitingForMapping(): bool
    {
        return self::STATUS_MAPPING === $this->status;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function getTotalRows(): ?int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }

    public function getProgressedAt(): ?\DateTime
    {
        return $this->progressedAt;
    }

    public function setProgress(int $processedRows, int $totalRows, int $errorRows): void
    {
        $this->processedRows = $processedRows;
        $this->totalRows = $totalRows;
        $this->errorRows = $errorRows;
        $this->progressedAt = new \DateTime();
    }

    /**
     * Whether a dispatcher can still stop the import.
     *
     * Kept in step with the statuses ImportStatusManager::cancel() writes over.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }

    public function getItemsCancellation(): ?string
    {
        return $this->itemsCancellation;
    }

    public function getItemsToCancel(): ?int
    {
        return $this->itemsToCancel;
    }

    public function getCancelledItems(): int
    {
        return $this->cancelledItems;
    }

    public function getSkippedItems(): int
    {
        return $this->skippedItems;
    }

    public function setItemsCancellation(?string $itemsCancellation, ?int $itemsToCancel, int $cancelledItems, int $skippedItems): void
    {
        $this->itemsCancellation = $itemsCancellation;
        $this->itemsToCancel = $itemsToCancel;
        $this->cancelledItems = $cancelledItems;
        $this->skippedItems = $skippedItems;
    }

    /**
     * Once the import is over, and only once: a dispatcher can't cancel them again
     */
    public function canCancelItems(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)
            && is_null($this->itemsCancellation);
    }
}
