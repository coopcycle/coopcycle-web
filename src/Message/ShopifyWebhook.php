<?php

namespace AppBundle\Message;

class ShopifyWebhook
{
    public function __construct(
        private string $deliveryIri,
        private string $event,
    ) {}

    public function getDeliveryIri(): string
    {
        return $this->deliveryIri;
    }

    public function getEvent(): string
    {
        return $this->event;
    }
}
