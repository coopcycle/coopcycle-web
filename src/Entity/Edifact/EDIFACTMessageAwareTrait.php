<?php

namespace AppBundle\Entity\Edifact;

use Doctrine\Common\Collections\ArrayCollection;

trait EDIFACTMessageAwareTrait
{

    protected $edifactMessages;

    /**
     * @return mixed
     */
    public function getEdifactMessages()
    {
        return $this->edifactMessages;
    }

    public function getImportMessage(): ?EDIFACTMessage
    {
        return collect($this->edifactMessages)->filter(fn (EDIFACTMessage $message) => $message->getMessageType() === "SCONTR")->first();
    }

    /**
     * @param mixed $edifactMessages
     */
    public function addEdifactMessage(EDIFACTMessage $edifactMessage): self
    {
        $this->edifactMessages[] = $edifactMessage;
        return $this;
    }

}
