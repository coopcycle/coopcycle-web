<?php

namespace AppBundle\Message;

class ExportOrders {

    public function __construct(
        private \DateTime $from,
        private \DateTime $to,
        private bool $withMessenger = false,
        private ?string $locale = null
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

    public function isWithMessenger(): bool
    {
        return $this->withMessenger;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

}
