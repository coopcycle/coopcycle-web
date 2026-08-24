<?php

namespace AppBundle\Entity\Edifact;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Illuminate\Support\Collection as IlluminateCollection;

trait EDIFACTMessageAwareTrait
{
    protected $edifactMessages;

    public function getEdifactMessages(): Collection
    {
        return $this->edifactMessages ?? new ArrayCollection();
    }

    public function hasEdifactMessages(): bool
    {
        return $this->getEdifactMessages()->count() > 0;
    }

    public function getImportMessage(): ?EDIFACTMessage
    {
        foreach ($this->getEdifactMessages() as $message) {
            if (in_array($message->getMessageType(), [
                EDIFACTMessage::MESSAGE_TYPE_SCONTR,
                EDIFACTMessage::MESSAGE_TYPE_PICKUP,
                EDIFACTMessage::MESSAGE_TYPE_DISPOR,
            ])) {
                return $message;
            }
        }

        return null;
    }

    public function getImportReference(): ?string
    {
        return $this->getImportMessage()?->getReference();
    }

    public function getReports(): IlluminateCollection
    {
        return collect($this->getEdifactMessages())
            ->filter(fn (EDIFACTMessage $message) => $message->getMessageType() === EDIFACTMessage::MESSAGE_TYPE_REPORT)
            ->values();
    }

    public function hasReports(): bool
    {
        return $this->getReports()->count() > 0;
    }

    public function addEdifactMessage(EDIFACTMessage $edifactMessage): self
    {
        if (is_null($this->edifactMessages)) {
            $this->edifactMessages = new ArrayCollection();
        }
        $this->edifactMessages[] = $edifactMessage;

        return $this;
    }
}
