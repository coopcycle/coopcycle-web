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

    /**
     * Get the Zelty external code.
     */
    public function getZeltyCode(): ?string
    {
        return $this->metadata['zelty_code'] ?? null;
    }

    /**
     * Set the Zelty external code.
     */
    public function setZeltyCode(?string $code): self
    {
        $this->metadata['zelty_code'] = $code;

        return $this;
    }

    /**
     * Check if a Zelty code is set.
     */
    public function hasZeltyCode(): bool
    {
        return isset($this->metadata['zelty_code']) && $this->metadata['zelty_code'] !== null;
    }
}