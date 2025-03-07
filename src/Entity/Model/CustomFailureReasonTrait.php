<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Delivery\FailureReasonSet;

trait CustomFailureReasonTrait {

    protected ?FailureReasonSet $failureReasonSet;

    /**
     * @return ?FailureReasonSet
     */
    public function getFailureReasonSet(): ?FailureReasonSet
    {
        return $this->failureReasonSet;
    }

    /**
     * @param ?FailureReasonSet $failureReasonSet
     */
    public function setFailureReasonSet(?FailureReasonSet $failureReasonSet): void
    {
        $this->failureReasonSet = $failureReasonSet;
    }

}
