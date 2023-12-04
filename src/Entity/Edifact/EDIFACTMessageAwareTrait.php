<?php

namespace AppBundle\Entity\Edifact;

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

    /**
     * @param mixed $edifactMessages
     */
    public function setEdifactMessages($edifactMessages)
    {
        $this->edifactMessages = $edifactMessages;
        return $this;
    }

}
