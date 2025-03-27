<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Delivery\FailureReasonSet;

trait CustomFailureReasonTrait {

    protected ?FailureReasonSet $failureReasonSet;

    public function getFailureReasonSet(): ?FailureReasonSet
    {
        return $this->failureReasonSet;
    }

    public function setFailureReasonSet(?FailureReasonSet $failureReasonSet): void
    {
        $this->failureReasonSet = $failureReasonSet;
    }

}
