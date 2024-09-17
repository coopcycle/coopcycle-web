<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\ConfigurePaymentOutput;

class ConfigurePayment
{
    public function __invoke($data)
    {
        return new ConfigurePaymentOutput($data);
    }
}
