<?php

namespace AppBundle\Service;

use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use AppBundle\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PawapayManager
{
    public function __construct(
        private readonly HttpClientInterface $pawapayClient,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SettingsManager $settingsManager,
        private readonly OrderNumberAssignerInterface $orderNumberAssigner,
        private readonly LoggerInterface $logger,
        private string $locale,
        private bool $useApiV1 = false,
        private ?string $countryCode = null
    )
    {}

    public function createPaymentPage(PaymentInterface $payment, array $context = [])
    {
        if (null === $this->countryCode) {
            $this->logger->info(sprintf('Order #%d | PawapayManager::createPaymentPage | Pawapay country code is not set', $payment->getOrder()->getId()));
            return;
        }

        $order = $payment->getOrder();
        $customer = $order->getCustomer();

        // When ordering as guest
        if (null === $customer) {
            return;
        }

        if (null !== $order->getId()) {
            $this->orderNumberAssigner->assignNumber($order);
        }

        // Example phone number +233241234567
        $phoneNumber = $customer->getPhoneNumber();

        if ($this->useApiV1) {

            // https://docs.pawapay.io/v1/api-reference/toolkit/predict-correspondent
            $response = $this->pawapayClient->request('POST', 'v1/predict-correspondent', [
                'json' => [
                    'msisdn' => $phoneNumber
                ]
            ]);

            $data = $response->toArray();
            $phoneNumber = $data['msisdn'];
        } else {

            // https://docs.pawapay.io/v2/api-reference/toolkit/predict-provider
            $response = $this->pawapayClient->request('POST', 'v2/predict-provider', [
                'json' => [
                    'phoneNumber' => $phoneNumber
                ]
            ]);

            $data = $response->toArray();
            $phoneNumber = $data['phoneNumber'];
        }

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
            'country' => $this->countryCode,
            'reason' => sprintf('Order %s', $order->getNumber()),
            'metadata' => [
                [
                    'fieldName' => 'submerchantLegalName',
                    'fieldValue' => $this->settingsManager->get('company_legal_name')
                ],
                [
                    'fieldName' => 'submerchantSegment',
                    'fieldValue' => 'ECOMMERCE'
                ],
            ],
        ];

        if ($this->useApiV1) {
            $payload = array_merge($payload, [
                'amount' => strval($numberFormatter->format($order->getTotal() / 100)), // Make sure it is a string
                // Example phone number 233241234567
                'msisdn' => $phoneNumber,
            ]);
        } else {
            $payload = array_merge($payload, [
                'amountDetails' => [
                    "amount" => strval($numberFormatter->format($order->getTotal() / 100)), // Make sure it is a string
                    "currency" => strtoupper($this->currencyContext->getCurrencyCode())
                ],
                // Example phone number 233241234567
                'phoneNumber' => $phoneNumber,
            ]);
        }

        $this->logger->info(
            sprintf('Order #%d | PawapayManager::createPaymentPage | %s', $order->getId(), json_encode($payload))
        );

        $response = $this->pawapayClient->request('POST', ($this->useApiV1 ? 'v1/widget/sessions' : 'v2/paymentpage'), [
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
