<?php

namespace AppBundle\Payment;

use AppBundle\Service\StripeManager;
use Omnipay\Common\Message\ResponseInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class Gateway
{
    private $resolver;
    private $stripeManager;

    public function __construct(
        GatewayResolver $resolver,
        StripeManager $stripeManager)
    {
        $this->resolver = $resolver;
        $this->stripeManager = $stripeManager;
    }

    public function authorize(PaymentInterface $payment): ResponseInterface
    {
        $charge = $this->stripeManager->authorize($payment);

        $payment->setCharge($charge->id);

        return new StripeResponse($charge);
    }

    public function capture(PaymentInterface $payment): ResponseInterface
    {
        $this->stripeManager->capture($payment);

        return new StripeResponse([]);
    }
}
