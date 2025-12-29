<?php

namespace AppBundle\Action\Store;

use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Entity\Store;

class PaymentMethods
{
    public function __construct(
        private bool $cashEnabled
    ) {
    }

    public function __invoke(Store $data): PaymentMethodsOutput
    {
        $output = new PaymentMethodsOutput();

        if ($this->cashEnabled || $data->isCashOnDeliveryEnabled()) {
            $output->addMethod('cash_on_delivery');
        }

        return $output;
    }
}

