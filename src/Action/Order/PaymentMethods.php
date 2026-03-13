<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Payment\PaymentMethodsResolver;
use AppBundle\Sylius\Order\OrderInterface;

class PaymentMethods
{
    public function __construct(private PaymentMethodsResolver $paymentMethodsResolver)
    {
    }

    public function __invoke(OrderInterface $data): PaymentMethodsOutput
    {
        $output = new PaymentMethodsOutput();

        foreach ($this->paymentMethodsResolver->resolveForApi($data) as $type) {
            $output->addMethod($type);
        }

        return $output;
    }
}
