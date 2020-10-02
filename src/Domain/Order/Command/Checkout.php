<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class Checkout
{
    private $order;
    private $data;

    /**
     * @param OrderInterface $order
     * @param string|array|null $data
     */
    public function __construct(OrderInterface $order, $data = null)
    {
        $this->order = $order;

        if (is_string($data)) {
            $this->data = ['stripeToken' => $data ];
        } else {
            $this->data = $data;
        }
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getStripeToken()
    {
        return $this->data['stripeToken'] ?? null;
    }

    public function getData()
    {
        return $this->data;
    }
}

