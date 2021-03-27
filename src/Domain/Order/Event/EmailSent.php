<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class EmailSent extends Event implements DomainEvent
{
    private $recipient;

    public static function messageName(): string
    {
        return 'order:email_sent';
    }

    public function __construct(OrderInterface $order, $recipient)
    {
        parent::__construct($order);

        $this->recipient = $recipient;
    }

    public function toPayload()
    {
        $payload = parent::toPayload();

        return array_merge($payload, [
            'recipient' => $this->recipient,
        ]);
    }
}
