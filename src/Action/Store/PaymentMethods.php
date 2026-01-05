<?php

namespace AppBundle\Action\Store;

use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Entity\Store;
use AppBundle\Service\DeliveryOrderManager;

class PaymentMethods
{
    public function __construct(
        private DeliveryOrderManager $deliveryOrderManager
    ) {
    }

    public function __invoke(Store $data): PaymentMethodsOutput
    {
        $output = new PaymentMethodsOutput();

        $supportedMethods = $this->deliveryOrderManager->getSupportedPaymentMethods($data);
        foreach ($supportedMethods as $method) {
            $output->addMethod($method);
        }

        return $output;
    }
}

