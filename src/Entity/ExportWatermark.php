<?php

namespace AppBundle\Entity;

/**
 * Tracks how far the analytics export has got, per export type.
 *
 * The exports are incremental: each run selects the rows modified since the
 * watermark, and only moves the watermark forward once the run has succeeded.
 * A failed run therefore simply retries the same range on the next attempt.
 *
 * A null watermark means "never exported", which is what makes the very first
 * run a full export without needing a separate code path.
 */
class ExportWatermark
{
    const TYPE_TASKS = 'tasks';
    const TYPE_ORDERS = 'orders';

    /**
     * @var string
     */
    private $type;

    /**
     * @var \DateTimeInterface|null
     */
    private $watermarkAt;

    /**
     * @var \DateTimeInterface|null
     */
    private $lastRunAt;

    /**
     * @var int
     */
    private $lastRunRows = 0;

    /**
     * Rows the overlap window picked up that the watermark alone would not
     * have selected. Zero over a long period means the window is redundant
     * and can be disabled with --overlap-days=0.
     *
     * @var int
     */
    private $lastRunOverlapRows = 0;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getWatermarkAt(): ?\DateTimeInterface
    {
        return $this->watermarkAt;
    }

    public function setWatermarkAt(?\DateTimeInterface $watermarkAt): self
    {
        $this->watermarkAt = $watermarkAt;

        return $this;
    }

    public function getLastRunAt(): ?\DateTimeInterface
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?\DateTimeInterface $lastRunAt): self
    {
        $this->lastRunAt = $lastRunAt;

        return $this;
    }

    public function getLastRunRows(): int
    {
        return $this->lastRunRows;
    }

    public function setLastRunRows(int $lastRunRows): self
    {
        $this->lastRunRows = $lastRunRows;

        return $this;
    }

    public function getLastRunOverlapRows(): int
    {
        return $this->lastRunOverlapRows;
    }

    public function setLastRunOverlapRows(int $lastRunOverlapRows): self
    {
        $this->lastRunOverlapRows = $lastRunOverlapRows;

        return $this;
    }
}
