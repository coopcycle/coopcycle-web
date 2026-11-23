<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Entity\Store;
use AppBundle\Action\Delivery\ImportQueueCsv as CsvController;
use AppBundle\Action\Delivery\ImportQueueRedownload as RedownloadController;
use AppBundle\Action\Delivery\ImportQueueCancel as CancelController;
use AppBundle\Action\Delivery\ImportQueueCancelDeliveries as CancelDeliveriesController;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'DeliveryImportQueue',
    operations: [
        new Get(normalizationContext: ['groups' => ['delivery_import_queue']],
            security: 'is_granted(\'view\', object)'),
        new Get(
            uriTemplate: '/delivery_import_queues/{id}/csv',
            controller: CsvController::class,
            security: 'is_granted(\'view\', object)'
        ),
        new Get(
            uriTemplate: '/delivery_import_queues/{id}/redownload',
            controller: RedownloadController::class,
            security: 'is_granted(\'view\', object)'
        ),
        new Put(
            uriTemplate: '/delivery_import_queues/{id}/cancel',
            controller: CancelController::class,
            openapiContext: ['summary' => 'Cancels a delivery import that is pending or in progress'],
            security: 'is_granted(\'edit\', object)',
            // The status is written by the controller
            write: false,
            deserialize: false
        ),
        new Put(
            uriTemplate: '/delivery_import_queues/{id}/cancel_deliveries',
            controller: CancelDeliveriesController::class,
            openapiContext: ['summary' => 'Cancels the deliveries created by a delivery import, but those already under way'],
            security: 'is_granted(\'edit\', object)',
            // Written by the controller, the deliveries are cancelled by a worker
            write: false,
            deserialize: false
        )
    ],
    normalizationContext: ['groups' => ['delivery_import_queue']]
)]
class ImportQueue
{
    use Timestampable;

    const STATUS_PENDING = 'pending';
    const STATUS_STARTED = 'started';
    // The file is read, its deliveries are being saved: too late to cancel
    const STATUS_SAVING = 'saving';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Cancelling the deliveries of an import, once it's done
    const DELIVERIES_CANCELLING = 'cancelling';
    const DELIVERIES_CANCELLED = 'cancelled';

    protected $id;

    protected string $filename;

    protected Store $store;

    #[Assert\Type(type: 'string')]
    #[Groups(['delivery_import_queue'])]
    protected $status = self::STATUS_PENDING;

    protected $startedAt;

    protected $finishedAt;

    #[Groups(['delivery_import_queue'])]
    protected array $errors = [];

    /**
     * Unknown until a worker has read the file
     */
    #[Groups(['delivery_import_queue'])]
    protected ?int $totalRows = null;

    #[Groups(['delivery_import_queue'])]
    protected int $processedRows = 0;

    #[Groups(['delivery_import_queue'])]
    protected int $errorRows = 0;

    /**
     * When the worker last reported its progress, to tell a long import from a dead one
     */
    protected ?\DateTime $progressedAt = null;

    /**
     * Null until a dispatcher cancels the deliveries of the import
     */
    #[Groups(['delivery_import_queue'])]
    protected ?string $deliveriesCancellation = null;

    /**
     * Unknown until a worker has looked them up
     */
    #[Groups(['delivery_import_queue'])]
    protected ?int $deliveriesToCancel = null;

    #[Groups(['delivery_import_queue'])]
    protected int $cancelledDeliveries = 0;

    /**
     * Already under way, or with an order that can't be cancelled any more
     */
    #[Groups(['delivery_import_queue'])]
    protected int $skippedDeliveries = 0;

    public function getId()
    {
        return $this->id;
    }

    public function setStore(Store $store)
    {
        $this->store = $store;
    }

    public function getStore(): Store
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

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_STARTED], true);
    }

    public function getDeliveriesCancellation(): ?string
    {
        return $this->deliveriesCancellation;
    }

    public function getDeliveriesToCancel(): ?int
    {
        return $this->deliveriesToCancel;
    }

    public function getCancelledDeliveries(): int
    {
        return $this->cancelledDeliveries;
    }

    public function getSkippedDeliveries(): int
    {
        return $this->skippedDeliveries;
    }

    public function setDeliveriesCancellation(?string $deliveriesCancellation, ?int $deliveriesToCancel, int $cancelledDeliveries, int $skippedDeliveries): void
    {
        $this->deliveriesCancellation = $deliveriesCancellation;
        $this->deliveriesToCancel = $deliveriesToCancel;
        $this->cancelledDeliveries = $cancelledDeliveries;
        $this->skippedDeliveries = $skippedDeliveries;
    }

    /**
     * Once the import is over, and only once: a dispatcher can't cancel them again
     */
    public function canCancelDeliveries(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)
            && is_null($this->deliveriesCancellation);
    }
}
