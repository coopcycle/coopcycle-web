<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Payment\StripeTrait;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;
use Sylius\Component\Payment\Model\Payment as BasePayment;

class Payment extends BasePayment implements OrderAwareInterface
{
    use StripeTrait;

    protected $order;

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }
}
