<?php

namespace AppBundle\Payment\Gateway;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class Edenred implements GatewayInterface
{
    public function __construct(private EdenredClient $edenred)
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        $authorizationId = $this->edenred->authorizeTransaction($payment);
        $payment->setEdenredAuthorizationId($authorizationId);
    }

    public function capture(PaymentInterface $payment)
    {
        $captureId = $this->edenred->captureTransaction($payment);
        $payment->setEdenredCaptureId($captureId);
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        $refund = $payment->addRefund($amount);

        $this->edenred->refund($payment, $amount);
        $refund->setData(['edenred_transaction_id' => $payment->getEdenredAuthorizationId()]);

        return $refund;
    }
}
