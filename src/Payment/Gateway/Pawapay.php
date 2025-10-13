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
    public function __construct(
        private HttpClientInterface $pawapayClient,
        private CurrencyContextInterface $currencyContext,
        private string $locale,
        private string $countryCode
    )
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        // https://api.sandbox.pawapay.io/v2/predict-provider
        // $response = $pawapayClient->request('POST', 'v2/predict-provider', [
        //     'json' => [
        //         'phoneNumber' => '+233241234567'
        //     ]
        // ]);

        $order = $payment->getOrder();
        $customer = $order->getCustomer();

        $depositId = Uuid::uuid4()->toString();

        $numberFormatter = \NumberFormatter::create($this->locale, \NumberFormatter::DECIMAL);
        $numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        $payload = [
            'depositId' => $depositId,
            'returnUrl' => 'https://demo.coopcycle.org/pawaypay/return',
            'amountDetails' => [
                "amount" => strval($numberFormatter->format($order->getTotal() / 100)), // Make sure it is a string
                "currency" => strtoupper($this->currencyContext->getCurrencyCode())
            ],
            'country' => $this->countryCode,
            'phoneNumber' => $customer->getPhoneNumber(),
            // 'reason' => 'Lorem ipsum',
        ];

        $response = $this->pawapayClient->request('POST', 'v2/paymentpage', [
            'json' => $payload
        ]);

        dd($response->toArray());
    }

    public function capture(PaymentInterface $payment)
    {
        // $captureId = $this->edenred->captureTransaction($payment);
        // $payment->setEdenredCaptureId($captureId);
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        // $refund = $payment->addRefund($amount);

        // $this->edenred->refund($payment, $amount);
        // $refund->setData(['edenred_transaction_id' => $payment->getEdenredAuthorizationId()]);

        // return $refund;
    }
}

