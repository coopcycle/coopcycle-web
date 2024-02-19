<?php

namespace AppBundle\Entity\Edifact;

use Doctrine\Common\Collections\Collection;

trait EDIFACTMessageAwareTrait
{

    protected $edifactMessages;

    public function getEdifactMessages(): Collection
    {
        return $this->edifactMessages;
    }

    public function getImportMessage(): ?EDIFACTMessage
    {
        return collect($this->edifactMessages)->filter(fn (EDIFACTMessage $message) => $message->getMessageType() === "SCONTR")->first();
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
