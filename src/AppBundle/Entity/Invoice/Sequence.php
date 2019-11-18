<?php

namespace AppBundle\Entity\Invoice;

class Sequence
{
    /** @var mixed */
    protected $id;

    /** @var int */
    protected $index = 0;

    /** @var int */
    protected $version = 1;

    public function getId()
    {
        return $this->id;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function incrementIndex(): void
    {
        ++$this->index;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(?int $version): void
    {
        $this->version = $version;
    }
}
