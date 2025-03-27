<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Delivery\FailureReasonSet;

interface CustomFailureReasonInterface {


    public function getFailureReasonSet(): ?FailureReasonSet;

    public function setFailureReasonSet(?FailureReasonSet $failureReasonSet): void;

}
