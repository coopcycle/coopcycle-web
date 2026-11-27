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
            // A disputed gift card purchase has to stop the card being spent
            // while the dispute runs; otherwise the co-op pays restaurants
            // out of money it is about to lose.
            //
            // Adding these means existing instances must re-run app:setup --
            // CreateWebhookEndpointHandler updates enabled_events on the
            // endpoint it already created.
            'charge.dispute.created',
            'charge.dispute.closed',
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
