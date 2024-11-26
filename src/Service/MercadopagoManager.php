<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\PaymentMethod\PaymentMethodClient;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\PaymentMethod\PaymentMethodListResult;
use MercadoPago\Resources\Payment;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see https://www.mercadopago.com.mx/developers/es/guides/payments/api/other-features
 */
class MercadopagoManager
{
    private $settingsManager;
    private $logger;

    public function __construct(
        SettingsManager $settingsManager,
        LoggerInterface $logger)
    {
        $this->settingsManager = $settingsManager;
        $this->logger = $logger;
    }

    public function configure()
    {
        MercadoPagoConfig::setAccessToken($this->settingsManager->get('mercadopago_access_token'));
    }

    /**
     * @see https://mercadopago.com/developers/en/docs/checkout-bricks/card-payment-brick/payment-submission
     * @return Payment
     */
    public function authorize(PaymentInterface $payment): Payment
    {
        $order = $payment->getOrder();
        $restaurant = $order->getRestaurant();

        $applicationFee = 0;
        $accessToken = null;
        if (null !== $restaurant) {
            $account = $restaurant->getMercadopagoAccount();
            if ($account) {
                $applicationFee = $order->getFeeTotal();
                $accessToken = $account->getAccessToken();
            }
        }

        if (null !== $accessToken) {
            MercadoPagoConfig::setAccessToken($accessToken);
        } else {
            $this->configure();
        }

        $order = $payment->getOrder();

        $client = new PaymentClient();

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            sprintf('X-Idempotency-Key: %s', Uuid::uuid4()->toString())
        ]);

        $mpPaymentMethod = $this->getPaymentMethod($payment);

        $payload = [
            'transaction_amount' => ($payment->getAmount() / 100),
            'token' => $payment->getStripeToken(),
            'description' => sprintf('Order %s', $order->getNumber()),
            'installments' => (int) ($payment->getMercadopagoInstallments() ?? 1),
            'payment_method_id' => $payment->getMercadopagoPaymentMethod(),
            'capture' => null !== $mpPaymentMethod && $mpPaymentMethod->deferred_capture === 'supported',
            'issuer_id' => $payment->getMercadopagoIssuer(),
            'payer' => [
                'email' => $payment->getMercadopagoPayerEmail(),
                // 'identification' => [
                //     'type' => $_POST['<IDENTIFICATION_TYPE'],
                //     'number' => $_POST['<NUMBER>']
                // ]
            ]
        ];

        if ($applicationFee > 0) {
            $payload['application_fee'] = ($applicationFee / 100);
        }

        return $client->create($payload, $requestOptions);
    }

    /**
     * @see https://www.mercadopago.com/developers/en/docs/checkout-api/payment-management/capture-authorized-payment
     * @return Payment
     */
    public function capture(PaymentInterface $payment): Payment
    {
        // FIXME: should be refactored

        $order = $payment->getOrder();

        $accessToken = null;
        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount();
            if ($account) {
                $accessToken = $account->getAccessToken();
            }
        }

        if (null !== $accessToken) {
            MercadoPagoConfig::setAccessToken($accessToken);
        } else {
            $this->configure();
        }

        $client = new PaymentClient();

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            sprintf('X-Idempotency-Key: %s', Uuid::uuid4()->toString())
        ]);

        return $client->capture($payment->getMercadopagoPaymentId(), ($payment->getAmount() / 100), $requestOptions);
    }

    /**
     * @return Payment
     */
    public function getPayment(PaymentInterface $payment): Payment
    {
        $order = $payment->getOrder();

        $accessToken = null;
        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount();
            if ($account) {
                $accessToken = $account->getAccessToken();
            }
        }

        if (null !== $accessToken) {
            MercadoPagoConfig::setAccessToken($accessToken);
        } else {
            $this->configure();
        }

        $client = new PaymentClient();

        return $client->get($payment->getMercadopagoPaymentId());
    }

    /**
     * @return PaymentMethodListResult|null
     */
    public function getPaymentMethod(PaymentInterface $payment): ?PaymentMethodListResult
    {
        try {

            $this->configure();

            $client = new PaymentMethodClient();

            $result = $client->list();

            foreach ($result->data as $paymentMethod) {
                if ($paymentMethod->id === $payment->getMercadopagoPaymentMethod()) {
                    return $paymentMethod;
                }
            }
        } catch(\Exception $e) {
            $this->logger->error(
                sprintf('Mercadopago - Error %s while trying to read payment method with id %s', $e->getMessage(), $payment->getMercadopagoPaymentMethod())
            );
            return null;
        }

        $this->logger->error(
            sprintf('Mercadopago - Error MercadoPago\PaymentMethod not found for payment method with id %s', $payment->getMercadopagoPaymentMethod())
        );
        return null;
    }
}
