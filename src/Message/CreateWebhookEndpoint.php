<?php

namespace AppBundle\Message;

class CreateWebhookEndpoint
{
    private $url;
    private $mode;
    private $events = [];

    public function __construct(string $url, string $mode)
    {
        $this->url = $url;
        $this->mode = $mode;
        $this->events = [
            'account.application.deauthorized',
            'account.updated',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'charge.captured',
            'charge.succeeded',
            'charge.updated',
            // Used for Giropay legacy integration
            'source.chargeable',
            'source.failed',
            'source.canceled',
        ];
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
