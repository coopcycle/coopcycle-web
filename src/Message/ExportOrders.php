<?php

namespace AppBundle\Message;

class ExportOrders {

    public function __construct(
        private \DateTime $from,
        private \DateTime $to,
        private bool $withMessenger = false,
        private ?string $locale = null,
        private bool $withBillingMethod = false
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

    public function isWithBillingMethod(): bool
    {
        return $this->withBillingMethod;
    }
}
