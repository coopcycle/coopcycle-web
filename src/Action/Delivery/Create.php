<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Pricing\PricingManager;

class Create
{
    public function __construct(private PricingManager $pricingManager)
    {}

    public function __invoke(Delivery $data)
    {
        $this->pricingManager->createOrder($data);

        return $data;
    }
}
