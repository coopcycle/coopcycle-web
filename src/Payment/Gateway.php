<?php

namespace AppBundle\Payment;

use AppBundle\Entity\Refund;
use Sylius\Component\Payment\Model\PaymentInterface;

class Gateway implements GatewayInterface
{
    public function __construct(
        private GatewayResolver $resolver,
        private array $gateways)
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        return $this->resolveGateway($payment)->authorize($payment, $context);
    }

    public function capture(PaymentInterface $payment)
    {
        return $this->resolveGateway($payment)->capture($payment);
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        return $this->resolveGateway($payment)->refund($payment, $amount);
    }

    private function resolveGateway(PaymentInterface $payment): GatewayInterface
    {
        $method = $payment->getMethod();

        if ($method && 'EDENRED' === $method->getCode()) {

            return $this->gateways['edenred'];
        }

        // A gift card is the co-op's own money moving between its own
        // books -- no external gateway is involved at all, which is why it
        // is matched on the method code here rather than resolved through
        // GatewayResolver's country/provider logic.
        if ($method && 'GIFT_CARD' === $method->getCode()) {

            return $this->gateways['gift_card'];
        }

        switch ($this->resolver->resolveForPayment($payment)) {
            case 'paygreen':
                return $this->gateways['paygreen'];
            case 'mercadopago':
                return $this->gateways['mercadopago'];
            case 'stripe':
            default:

                return $this->gateways['stripe'];
        }
    }
}
