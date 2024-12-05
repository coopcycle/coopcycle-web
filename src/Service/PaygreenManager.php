<?php

namespace AppBundle\Service;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Hashids\Hashids;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use Paygreen\Sdk\Payment\V3\Model as PaygreenModel;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

class PaygreenManager
{
    public function __construct(
        private PaygreenClient $paygreenClient,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private Hashids $hashids8,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentFactoryInterface $paymentFactory,
        private string $country)
    {}

    private function authenticate()
    {
        // https://developers.paygreen.fr/docs/auth#create-a-bearer-access-token
        $response = $this->paygreenClient->authenticate();
        $data = json_decode($response->getBody()->getContents())->data;
        $this->paygreenClient->setBearer($data->token);
    }

    public function capture(PaymentInterface $payment)
    {
        $this->authenticate();

        $details = $payment->getDetails();
        $response = $this->paygreenClient->capturePaymentOrder($details['paygreen_payment_order_id']);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Could not capture payment');
        }

        // TODO Store the transaction ID
    }

    public function createPaymentOrder(PaymentInterface $payment, ?string $instrument = null)
    {
        $this->authenticate();

        $order = $payment->getOrder();

        // TODO Remove this
        $rand = bin2hex(random_bytes(5));

        $reference = sprintf('ord_%s_%s', $this->hashids8->encode($order->getId()), $rand);
        $shopId = $order->getRestaurant()->getPaygreenShopId();

        if (null !== $instrument && !empty($instrument)) {

            $address = $this->getAddress($order);

            $paymentOrder = new PaygreenModel\PaymentOrder();
            $paymentOrder->setReference($reference);
            $paymentOrder->setInstrument($instrument);
            $paymentOrder->setAmount($order->getTotal());
            $paymentOrder->setShopId($shopId);
            // $paymentOrder->setFees(10); // set the fees value
            $paymentOrder->setAutoCapture(false);
            $paymentOrder->setCurrency($payment->getCurrencyCode());
            $paymentOrder->setShippingAddress($address);
            $paymentOrder->setDescription(sprintf('Order %s', $order->getNumber()));

            $response = $this->paygreenClient->createPaymentOrder($paymentOrder);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception($data['detail'] ?? $data['message']);
            }

            $payment->setPaygreenPaymentOrderId($data['data']['id']);
            $payment->setPaygreenObjectSecret($data['data']['object_secret']);

            return;
        }

        // $response = $this->paygreenClient->listPaymentOrder($reference);
        // echo '<pre>';
        // print_r(json_decode($response->getBody()->getContents()));
        // exit;

        if ($payment->hasPaygreenPaymentOrderId()) {
            $response = $this->paygreenClient->getPaymentOrder($payment->getPaygreenPaymentOrderId());
            if ($response->getStatusCode() === 200) {

                $data = json_decode($response->getBody()->getContents(), true);

                if ($data['data']['status'] === 'payment_order.pending') {
                    $expiresAt = Carbon::parse($data['data']['expires_at'])->tz(date_default_timezone_get());

                    $isExpired = $expiresAt->isBefore(Carbon::now());

                    if (!$isExpired) {
                        return;
                    }
                }
            }
        }

        $shippingAddress = $order->getShippingAddress();

        $address = new PaygreenModel\Address();
        $address->setStreetLineOne($shippingAddress->getStreetAddress());
        $address->setCity($shippingAddress->getAddressLocality());
        $address->setCountryCode(strtoupper($this->country));
        $address->setPostalCode($shippingAddress->getPostalCode());

        if (!$order->getCustomer()->hasPaygreenBuyerId()) {

            $fullNameParts = explode(' ', $order->getCustomer()->getFullName(), 2);
            $firstName = $fullNameParts[0] ?? '';
            $lastName = $fullNameParts[1] ?? '';

            $buyer = new PaygreenModel\Buyer();
            $buyer->setReference(sprintf('cus_%s', $this->hashids8->encode($order->getCustomer()->getId())));
            $buyer->setEmail($order->getCustomer()->getEmailCanonical());
            $buyer->setFirstName(!empty($firstName) ? $firstName : 'N/A');
            $buyer->setLastName(!empty($lastName) ? $lastName : 'N/A');
            $buyer->setBillingAddress($address);

            $response = $this->paygreenClient->createBuyer($buyer);
            $data = json_decode($response->getBody()->getContents(), true);

            $order->getCustomer()->setPaygreenBuyerId($data['data']['id']);
        }

        $buyer = new PaygreenModel\Buyer();
        $buyer->setId($order->getCustomer()->getPaygreenBuyerId());

        $this->orderNumberAssigner->assignNumber($order);

        $paymentOrder = new PaygreenModel\PaymentOrder();
        $paymentOrder->setReference($reference);
        $paymentOrder->setBuyer($buyer);
        $paymentOrder->setAmount($order->getTotal());
        $paymentOrder->setAutoCapture(false);
        $paymentOrder->setCurrency($payment->getCurrencyCode());
        $paymentOrder->setShippingAddress($address);
        $paymentOrder->setDescription(sprintf('Order %s', $order->getNumber()));
        $paymentOrder->setShopId($shopId);

        // platforms is required when fees is set
        // Impossible to process fees on Payment Orders setup with non-wallet platforms.
        // You must only specify a platform with a wallet behavior (e.g. bank_card)
        // $paymentOrder->setPlatforms(['bank_card']);
        // $paymentOrder->setFees($order->getFeeTotal());

        $response = $this->paygreenClient->createPaymentOrder($paymentOrder);

        $data = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception($data['detail'] ?? $data['message']);
        }

        $payment->setPaygreenPaymentOrderId($data['data']['id']);
        $payment->setPaygreenObjectSecret($data['data']['object_secret']);
    }

    public function getEnabledPlatforms($shopId): array
    {
        $this->authenticate();

        $response = $this->paygreenClient->listPaymentConfig($shopId);

        $data = json_decode($response->getBody()->getContents(), true);

        $enabled = array_filter($data['data'], fn ($c) => $c['status'] === 'payment_config.enabled');

        return array_map(fn ($c) => $c['platform'], $enabled);
    }

    public function getBuyerForOrder(OrderInterface $order): PaygreenModel\Buyer
    {
        $this->authenticate();

        $customer = $order->getCustomer();

        if (!$customer->hasPaygreenBuyerId()) {

            $fullNameParts = explode(' ', $customer->getFullName(), 2);
            $firstName = $fullNameParts[0] ?? '';
            $lastName = $fullNameParts[1] ?? '';

            $shippingAddress = $order->getShippingAddress();

            $address = new PaygreenModel\Address();
            $address->setStreetLineOne($shippingAddress->getStreetAddress());
            $address->setCity($shippingAddress->getAddressLocality());
            $address->setCountryCode(strtoupper($this->country));
            $address->setPostalCode($shippingAddress->getPostalCode());

            $buyer = new PaygreenModel\Buyer();
            $buyer->setReference(sprintf('cus_%s', $this->hashids8->encode($customer->getId())));
            $buyer->setEmail($customer->getEmailCanonical());
            $buyer->setFirstName(!empty($firstName) ? $firstName : 'N/A');
            $buyer->setLastName(!empty($lastName) ? $lastName : 'N/A');
            $buyer->setBillingAddress($address);

            $response = $this->paygreenClient->createBuyer($buyer);
            $data = json_decode($response->getBody()->getContents(), true);

            $customer->setPaygreenBuyerId($data['data']['id']);
        }

        $buyer = new PaygreenModel\Buyer();
        $buyer->setId($customer->getPaygreenBuyerId());

        return $buyer;
    }

    /**
     * @return array|null
     */
    public function getPaymentOrder($id)
    {
        $this->authenticate();

        $response = $this->paygreenClient->getPaymentOrder($id);

        if ($response->getStatusCode() === 200) {
            $payload = json_decode($response->getBody()->getContents(), true);

            return $payload['data'];
        }

        return null;
    }

    public function getPaymentsFromPaymentOrder($id)
    {
        $po = $this->getPaymentOrder($id);

        $payments = [];
        foreach ($po['transactions'] as $transaction) {
            if ('transaction.authorized' === $transaction['status']) {
                foreach ($transaction['operations'] as $operation) {
                    if ('operation.authorized' === $operation['status']) {

                        $paymentMethodCode = $operation['instrument']['platform'];
                        if ('bank_card' === $paymentMethodCode) {
                            $paymentMethodCode = 'card';
                        }

                        $method = $this->paymentMethodRepository->findOneByCode(strtoupper($paymentMethodCode));

                        $payment = $this->paymentFactory->createWithAmountAndCurrencyCode(
                            $operation['amount'],
                            strtoupper($po['currency'])
                        );
                        $payment->setMethod($method);
                        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

                        $payment->setDetails([
                            'paygreen_payment_order_id' => $id,
                            'paygreen_operation_id' => $operation['id'],
                        ]);

                        $payments[] = $payment;
                    }
                }
            }
        }

        return $payments;
    }

    /**
     * @return array|null
     */
    public function getInstrument($id)
    {
        $this->authenticate();

        $response = $this->paygreenClient->getInstrument($id);

        if ($response->getStatusCode() === 200) {
            $payload = json_decode($response->getBody()->getContents(), true);

            return $payload['data'];
        }

        return null;
    }

    private function getAddress(OrderInterface $order): PaygreenModel\Address
    {
        $shippingAddress = $order->getShippingAddress();

        $address = new PaygreenModel\Address();
        $address->setStreetLineOne($shippingAddress->getStreetAddress());
        $address->setCity($shippingAddress->getAddressLocality());
        $address->setCountryCode(strtoupper($this->country));
        $address->setPostalCode($shippingAddress->getPostalCode());

        return $address;
    }
}
