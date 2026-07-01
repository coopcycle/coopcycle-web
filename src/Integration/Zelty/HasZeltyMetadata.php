<?php

namespace AppBundle\Integration\Zelty;

/**
 * Trait for entities that hold Zelty external reference metadata.
 */
trait HasZeltyMetadata
{
    /**
     * @var array|null
     */
    private ?array $metadata = null;

    /**
     * Get all metadata.
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Set metadata array.
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getZeltyId(): ?string
    {
        return $this->metadata['zelty_id'] ?? null;
    }

    public function setZeltyId(?string $id): self
    {
        $this->metadata['zelty_id'] = $id;

        return $this;
    }

    public function hasZeltyId(): bool
    {
        return isset($this->metadata['zelty_id']) && $this->metadata['zelty_id'] !== null;
    }

    public function getZeltyInternalId(): ?string
    {
        return $this->metadata['zelty_internal_id'] ?? null;
    }

    public function setZeltyInternalId(?string $internalId): self
    {
        $this->metadata['zelty_internal_id'] = $internalId;

        return $this;
    }
}
