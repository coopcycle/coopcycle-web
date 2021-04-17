<?php

namespace AppBundle\Edenred;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Client as BaseClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Payment\Model\PaymentInterface;
use Webmozart\Assert\Assert;

class Client extends BaseClient
{
    private $logger;

    public function __construct(
        array $config = [],
        string $paymentClientId,
        string $paymentClientSecret,
        RefreshTokenHandler $refreshTokenHandler,
        Authentication $authentication,
        LoggerInterface $logger = null
    )
    {
        if (isset($config['handler']) && $config['handler'] instanceof HandlerStack) {
            $stack = $config['handler'];
        } else {
            $stack = HandlerStack::create();
            $config['handler'] = $stack;
        }

        $stack->push($refreshTokenHandler);

        parent::__construct($config);

        $this->paymentClientId = $paymentClientId;
        $this->paymentClientSecret = $paymentClientSecret;
        $this->authentication = $authentication;

        $this->logger = $logger ?? new NullLogger();
    }

    public function getBalance(Customer $customer): int
    {
        $userInfo = $this->authentication->userInfo($customer);

        $credentials = $customer->getEdenredCredentials();

        try {
            // https://documenter.getpostman.com/view/10405248/TVewaQQX#82e953fc-9110-4246-8a78-aba888b70b31
            $response = $this->request('GET', sprintf('/v1/users/%s', $userInfo['username']), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $credentials->getAccessToken()),
                    'X-Client-Id' => $this->paymentClientId,
                    'X-Client-Secret' => $this->paymentClientSecret,
                ],
                'oauth_credentials' => $credentials,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return $data['data']['available_amount'] ?? 0;
        } catch (RequestException $e) {
            $this->logger->error(sprintf(
                'Could not get customer balance: "%s"',
                (string) $e->getResponse()->getBody()
            ));

            // We do *NOT* rethrow the exception,
            // we just return a balance of zero.
            // This way, if the Edenred server has problems,
            // it doesn't break the checkout.
        }

        return 0;
    }

    /**
     * @param PaymentInterface $payment
     * @return string The authorization ID
     */
    public function authorizeTransaction(PaymentInterface $payment): string
    {
        $order = $payment->getOrder();

        $body = [
            "mid" => $order->getRestaurant()->getEdenredMerchantId(),
            "order_ref" => $order->getNumber(),
            "amount" => $payment->getAmountForMethod('EDENRED'),
            "capture_mode" => "manual",
            "tstamp" => (new \DateTime())->format(\DateTime::ATOM),
        ];

        Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);

        $credentials = $order->getCustomer()->getEdenredCredentials();

        // https://documenter.getpostman.com/view/10405248/TVewaQQX#42a5e69d-898b-41b9-b37e-9d28c23135c8
        try {
            $response = $this->request('POST', '/v1/transactions', [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $credentials->getAccessToken()),
                    'X-Client-Id' => $this->paymentClientId,
                    'X-Client-Secret' => $this->paymentClientSecret,
                ],
                'json' => $body,
                'oauth_credentials' => $credentials,
            ]);

            $responseData = json_decode((string) $response->getBody(), true);

            return $responseData['data']['authorization_id'];
        } catch (RequestException $e) {
            $this->logger->error(sprintf(
                'Could not authorize transaction: "%s"',
                (string) $e->getResponse()->getBody()
            ));

            throw $e;
        }
    }

    /**
     * @param PaymentInterface $payment
     * @return string The capture ID
     */
    public function captureTransaction(PaymentInterface $payment): string
    {
        $order = $payment->getOrder();

        $body = [
            'amount' => $payment->getAmountForMethod('EDENRED'),
            'tstamp' => (new \DateTime())->format(\DateTime::ATOM),
        ];

        Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);

        $credentials = $order->getCustomer()->getEdenredCredentials();

        // https://documenter.getpostman.com/view/10405248/TVewaQQX#93c0ef51-7526-46a2-9c48-967692f51d7f
        try {
            $response = $this->request('POST', sprintf('/v1/transactions/%s/actions/capture', $payment->getEdenredAuthorizationId()), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $credentials->getAccessToken()),
                    'X-Client-Id' => $this->paymentClientId,
                    'X-Client-Secret' => $this->paymentClientSecret,
                ],
                'json' => $body,
                'oauth_credentials' => $credentials,
            ]);

            $responseData = json_decode((string) $response->getBody(), true);

            return $responseData['data']['capture_id'];
        } catch (RequestException $e) {
            $this->logger->error(sprintf(
                'Could not authorize transaction: "%s"',
                (string) $e->getResponse()->getBody()
            ));

            throw $e;
        }
    }

    public function splitAmounts(OrderInterface $order)
    {
        // FIXME
        // If the order is click & collect, customer can pay it all

        $total = $order->getTotal();

        $deliveryFee = $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        $packagingFee = $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT);

        $notPayableAmount = $deliveryFee + $packagingFee;
        $payableAmount = $total - $notPayableAmount;

        $balance = $this->getBalance($order->getCustomer());

        if ($payableAmount > $balance) {
            $rest = $payableAmount - $balance;
            $notPayableAmount += $rest;
            $payableAmount = $balance;
        }

        return [
            'edenred' => $payableAmount,
            'card' => $notPayableAmount,
        ];
    }

    public function cancelTransaction(PaymentInterface $payment)
    {
        $order = $payment->getOrder();

        $body = [
            'amount' => $payment->getAmountForMethod('EDENRED'),
            'tstamp' => (new \DateTime())->format(\DateTime::ATOM),
        ];

        Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);

        $credentials = $order->getCustomer()->getEdenredCredentials();

        // https://documenter.getpostman.com/view/10405248/TVewaQQX#daa3d033-f7d9-4c0b-a0c4-db3614596895
        try {
            $response = $this->request('POST', sprintf('/v1/transactions/%s/actions/cancel', $payment->getEdenredAuthorizationId()), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $credentials->getAccessToken()),
                    'X-Client-Id' => $this->paymentClientId,
                    'X-Client-Secret' => $this->paymentClientSecret,
                ],
                'json' => $body,
                'oauth_credentials' => $credentials,
            ]);

            $responseData = json_decode((string) $response->getBody(), true);

            return $responseData['data']['cancel_id'];
        } catch (RequestException $e) {
            $this->logger->error(sprintf(
                'Could not authorize transaction: "%s"',
                (string) $e->getResponse()->getBody()
            ));

            throw $e;
        }
    }
}
