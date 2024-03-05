<?php

namespace AppBundle\Entity\Edifact;

use AppBundle\Presenter\EDIFACTMessagePresenter;
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

    public function getEdifactMessagesTimeline(): array
    {
        return array_map(fn (EDIFACTMessage $message) => EDIFACTMessagePresenter::toTimeline($message), $this->edifactMessages->toArray());
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
