<?php

namespace AppBundle\Payment\Gateway;

use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Pawapay implements GatewayInterface
{
    public function __construct()
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        // Not implemented
    }

    public function capture(PaymentInterface $payment)
    {
        // Not implemented
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        $refund = $payment->addRefund($amount);

        $refundId = Uuid::uuid4()->toString();

        $numberFormatter = \NumberFormatter::create($this->locale, \NumberFormatter::DECIMAL);
        $numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $numberFormatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);

        $response = $this->pawapayClient->request('POST', 'v2/refunds', [
            'json' => [
                'refundId' => $refundId,
                'depositId' => $payment->getPawapayDepositId(),
                'amount' => strval($numberFormatter->format($amount / 100)), // Make sure it is a string
                'currency' => strtoupper($this->currencyContext->getCurrencyCode())
            ]
        ]);

        $refund->setData(['pawapay_refund_id' => $refundId]);

        return $refund;
    }
}

