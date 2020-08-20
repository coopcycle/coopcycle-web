<?php

namespace AppBundle\Payment;

use AppBundle\Service\MercadopagoManager;
use AppBundle\Service\StripeManager;
use Omnipay\Common\Message\ResponseInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class Gateway
{
    private $resolver;
    private $stripeManager;
    private $mercadopagoManager;

    public function __construct(
        GatewayResolver $resolver,
        StripeManager $stripeManager,
        MercadopagoManager $mercadopagoManager)
    {
        $this->resolver = $resolver;
        $this->stripeManager = $stripeManager;
        $this->mercadopagoManager = $mercadopagoManager;
    }

    public function authorize(PaymentInterface $payment): ResponseInterface
    {
        switch ($this->resolver->resolve()) {
            case 'mercadopago':
                $p = $this->mercadopagoManager->authorize($payment);

                $payment->setCharge($p->id);

                return new MercadopagoResponse($p);
            case 'stripe':
            default:

                $charge = $this->stripeManager->authorize($payment);

                $payment->setCharge($charge->id);

                return new StripeResponse($charge);
        }
    }

    public function capture(PaymentInterface $payment): ResponseInterface
    {
        switch ($this->resolver->resolve()) {
            case 'mercadopago':
                $this->mercadopagoManager->capture($payment);

                return new MercadopagoResponse([]);
            case 'stripe':
            default:

                $this->stripeManager->capture($payment);

                return new StripeResponse([]);
        }
    }
}
