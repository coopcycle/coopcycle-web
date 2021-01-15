<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;

class OrderEventCollection extends ArrayCollection
{
    private static $eventNames = [
        'order:created',
        'order:accepted',
        'order:refused',
        'order:picked',
        'order:dropped',
        'order:cancelled',
    ];

    public function __construct(OrderInterface $order)
    {
        $events = $order->getEvents()->filter(function ($event) {
            return in_array($event->getType(), self::$eventNames);
        });

        parent::__construct($events->toArray());
    }

    // TODO Use normalizer
    public function toArray()
    {
        $elements = [];

        foreach ($this as $event) {
            $elements[] = [
                'name' => $event->getType(),
                'createdAt' => $event->getCreatedAt()->format(\DateTime::ATOM),
            ];
        }

        return $elements;
    }
}
