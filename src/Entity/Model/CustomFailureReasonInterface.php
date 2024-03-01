<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Delivery\FailureReasonSet;

interface CustomFailureReasonInterface {


    /**
     * @return ?FailureReasonSet
     */
    public function getFailureReasonSet(): ?FailureReasonSet;

    /**
     * @param ?FailureReasonSet $failureReasonSet
     */
    public function setFailureReasonSet(?FailureReasonSet $failureReasonSet): void;

}
