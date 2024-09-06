<?php

namespace AppBundle\Message;

class ExportTasks {

    public function __construct(
        private \DateTime $from,
        private \DateTime $to
    )
    { }

    public function getFrom(): \DateTime
    {
        return $this->from;
    }

    public function getTo(): \DateTime
    {
        return $this->to;
    }
}
