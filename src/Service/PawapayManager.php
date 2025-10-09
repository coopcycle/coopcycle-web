<?php

namespace AppBundle\Service;

use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PawapayManager
{
    public function __construct(
        private HttpClientInterface $pawapayClient,
        private CurrencyContextInterface $currencyContext,
        private UrlGeneratorInterface $urlGenerator,
        private string $locale,
        private string $countryCode
    )
    {}

    public function createPaymentPage(PaymentInterface $payment, array $context = [])
    {
        $order = $payment->getOrder();
        $customer = $order->getCustomer();

        // https://api.sandbox.pawapay.io/v2/predict-provider
        $response = $this->pawapayClient->request('POST', 'v2/predict-provider', [
            'json' => [
                'phoneNumber' => $customer->getPhoneNumber()
            ]
        ]);

        $data = $response->toArray();
        $phoneNumber = $data['phoneNumber'];

        $depositId = Uuid::uuid4()->toString();

        $numberFormatter = \NumberFormatter::create($this->locale, \NumberFormatter::DECIMAL);
        $numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $numberFormatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);

        $payload = [
            'depositId' => $depositId,
            // Even in sandbox mode, Pawapay does not allow http://localhost
            // When developing, replace this with https://demo.coopcycle.org/pawapay/return for example
            'returnUrl' => $this->urlGenerator->generate('pawapay_return_url', referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
            'amountDetails' => [
                "amount" => strval($numberFormatter->format($order->getTotal() / 100)), // Make sure it is a string
                "currency" => strtoupper($this->currencyContext->getCurrencyCode())
            ],
            'country' => $this->countryCode,
            // Example phone number 233241234567
            'phoneNumber' => $phoneNumber,
            // 'reason' => 'Lorem ipsum',
        ];

        $response = $this->pawapayClient->request('POST', 'v2/paymentpage', [
            'json' => $payload
        ]);

        $data = $response->toArray();

        $payment->setPawapayDepositId($depositId);
        $payment->setPawapayPaymentPageUrl($data['redirectUrl']);
    }

    public function getDeposit(string $depositId)
    {
        $response = $this->pawapayClient->request('GET', sprintf('v2/deposits/%s', $depositId));

        $payload = $response->toArray();

        return $payload['data'];
    }
}
