<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OrderPriceRecalculated extends Event implements DomainEvent, HasIconInterface
{

    public function __construct(
        OrderInterface  $order,
        private int     $new_price,
        private int     $old_price,
        private ?string $caused_by = null
    )
    {
        parent::__construct($order);
    }

    public function getNewPrice(): int
    {
        return $this->new_price;
    }

    public function getOldPrice(): int
    {
        return $this->old_price;
    }

    public function getCausedby(): ?string
    {
        return $this->caused_by;
    }

    public function toPayload()
    {
        return [
            'price'     => $this->getNewPrice(),
            'old_price' => $this->getOldPrice(),
            'caused_by' => $this->getCausedby()
        ];
    }

    public function normalize(NormalizerInterface $serializer)
    {
        return array_merge(
            parent::normalize($serializer),
            ['caused_by' => $this->getCausedby()]
        );
    }

    public static function iconName()
    {
        return 'calculator';
    }

    public static function messageName(): string
    {
        return 'order:price_recalculated';
    }
}
