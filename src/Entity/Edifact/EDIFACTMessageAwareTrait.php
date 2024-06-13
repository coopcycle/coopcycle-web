<?php

namespace AppBundle\Entity\Edifact;

use Doctrine\Common\Collections\Collection;
use Illuminate\Support\Collection as IlluminateCollection;

trait EDIFACTMessageAwareTrait
{

    protected $edifactMessages;

    public function getEdifactMessages(): Collection
    {
        return $this->edifactMessages;
    }

    public function getImportMessage(): ?EDIFACTMessage
    {
        return collect($this->edifactMessages)
            ->filter(fn (EDIFACTMessage $message) => $message->getMessageType() === EDIFACTMessage::MESSAGE_TYPE_SCONTR)
            ->first();
    }

    public function getReports(): IlluminateCollection
    {
        return collect($this->edifactMessages)
            ->filter(fn (EDIFACTMessage $message) => $message->getMessageType() === EDIFACTMessage::MESSAGE_TYPE_REPORT);
    }

    public function hasReports(): bool
    {
        return $this->getReports()->count() > 0;
    }

    /**
     * @param EDIFACTMessage $edifactMessage
     */
    public function addEdifactMessage(EDIFACTMessage $edifactMessage): self
    {
        $this->edifactMessages[] = $edifactMessage;
        return $this;
    }

}
