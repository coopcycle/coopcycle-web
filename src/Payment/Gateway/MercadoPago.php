<?php

namespace AppBundle\Payment\Gateway;

use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use AppBundle\Service\MercadopagoManager;
use Omnipay\Common\Message\ResponseInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class MercadoPago
{
    public function __construct(private MercadopagoManager $mercadopagoManager)
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        $payment->setStripeToken($context['token']);
        $p = $this->mercadopagoManager->authorize($payment);
        $payment->setCharge($p->id);
    }

    public function capture(PaymentInterface $payment)
    {
        $this->mercadopagoManager->capture($payment);
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        throw new \RuntimeException('Refunding with MercadoPago is not implemented yet');
    }
}
