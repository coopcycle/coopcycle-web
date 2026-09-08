<?php

namespace AppBundle\Message;

class ExportTasks {

    /**
     * @param bool $byModifiedAt Select on the modification date rather than on
     *                           the delivery window, and take the dates as
     *                           given instead of widening them to whole days.
     *                           This is what makes the export incremental: a
     *                           task completed days after it was scheduled is
     *                           picked up by the run that follows the change.
     */
    public function __construct(
        private \DateTime $from,
        private \DateTime $to,
        private bool $byModifiedAt = false
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

    public function isByModifiedAt(): bool
    {
        return $this->byModifiedAt;
    }
}
