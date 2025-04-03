<?php

namespace AppBundle\Service;

use AppBundle\Exception\PaygreenException;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\Defaults;
use Carbon\Carbon;
use Hashids\Hashids;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use Paygreen\Sdk\Payment\V3\Model as PaygreenModel;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaygreenManager
{
    public function __construct(
        private PaygreenClient $paygreenClient,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private Hashids $hashids8,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentFactoryInterface $paymentFactory,
        private UrlGeneratorInterface $urlGenerator,
        private ChannelContextInterface $channelContext,
        private AdjustmentFactoryInterface $adjustmentFactory,
        private Defaults $defaults,
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

        $po = $this->getPaymentOrder($payment->getPaygreenPaymentOrderId());

        // If there are multiple operations, the parent payment order might already have been captured
        if ($po['status'] !== 'payment_order.authorized') {
            return;
        }

        $response = $this->paygreenClient->capturePaymentOrder($payment->getPaygreenPaymentOrderId());
        if ($response->getStatusCode() !== 200) {
            throw new PaygreenException('Could not capture payment');
        }

        $payload = json_decode($response->getBody()->getContents(), true);
        $po = $payload['data'];

        // We also store the transaction fee

        $order = $payment->getOrder();

        $order->removeAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);

        $paygreenFee = 0;
        foreach ($po['transactions'] as $transaction) {
            if ($transaction['status'] === 'transaction.captured') {
                foreach ($transaction['operations'] as $operation) {
                    if ($operation['status'] === 'operation.captured') {
                        $paygreenFee += $operation['cost'] + $operation['fees'];
                    }
                }
            }
        }

        $feeAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::STRIPE_FEE_ADJUSTMENT,
            'Paygreen fee',
            $paygreenFee,
            $neutral = true
        );
        $order->addAdjustment($feeAdjustment);
    }

    public function createPaymentOrder(PaymentInterface $payment)
    {
        $this->authenticate();

        $order = $payment->getOrder();

        if (null === $order->getId() || null === $order->getShippingAddress() || null === $order->getCustomer()) {
            return;
        }

        $shopId = $order->getRestaurant()->getPaygreenShopId();
        $reference = sprintf('ord_%s', $this->hashids8->encode($order->getId()));

        // If a valid payment order already exists for this order, we cancel it
        $response = $this->paygreenClient->listPaymentOrder($reference);
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody()->getContents(), true);
            if (count($data['data']) > 0) {
                foreach ($data['data'] as $po) {
                    if ($po['status'] === 'payment_order.pending') {
                        $this->paygreenClient->cancelPaymentOrder($po['id']);
                    }
                }
            }
        }

        $address = $this->createAddress($order);

        if (!$order->getCustomer()->hasPaygreenBuyerId()) {

            $buyer = $this->createBuyer($order, $address);

            $response = $this->paygreenClient->createBuyer($buyer);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== 200) {
                throw new PaygreenException($data['detail'] ?? $data['message']);
            }

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
        $paymentOrder->setReturnUrl($this->getCallbackUrl('paygreen_return'));
        $paymentOrder->setCancelUrl($this->getCallbackUrl('paygreen_cancel'));
        $paymentOrder->setEligibleAmounts($this->getEligibleAmounts($order));

        // We can set platforms fees *ONLY* when the order is paid 100% by credit card
        if ('CARD' === $payment->getMethod()->getCode()) {
            $paymentOrder->setPlatforms(['bank_card']); // Avoid error "platforms is required when fees is set"
            $paymentOrder->setFees($order->getFeeTotal());
        }

        $response = $this->paygreenClient->createPaymentOrder($paymentOrder);

        $data = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() !== 200) {
            throw new PaygreenException($data['detail'] ?? $data['message']);
        }

        $payment->setPaygreenPaymentOrderId($data['data']['id']);
        $payment->setPaygreenObjectSecret($data['data']['object_secret']);
        $payment->setPaygreenHostedPaymentUrl($data['data']['hosted_payment_url']);

        return $data['data'];
    }

    public function getEnabledPlatforms($shopId): array
    {
        $this->authenticate();

        $response = $this->paygreenClient->listPaymentConfig($shopId);

        $data = json_decode($response->getBody()->getContents(), true);

        $enabled = array_filter($data['data'], fn ($c) => $c['status'] === 'payment_config.enabled');

        return array_map(fn ($c) => $c['platform'], $enabled);
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

    private function createAddress(OrderInterface $order): PaygreenModel\Address
    {
        $shippingAddress = $order->getShippingAddress();

        $address = new PaygreenModel\Address();
        $address->setStreetLineOne($shippingAddress->getStreetAddress());
        $address->setCity($shippingAddress->getAddressLocality() ?? $this->defaults->getAddressLocality());
        $address->setCountryCode(strtoupper($this->country));
        $address->setPostalCode($shippingAddress->getPostalCode() ?? $this->defaults->getPostalCode());

        return $address;
    }

    private function createBuyer(OrderInterface $order, PaygreenModel\Address $address): PaygreenModel\Buyer
    {
        $fullNameParts = explode(' ', $order->getCustomer()->getFullName(), 2);
        $firstName = $fullNameParts[0] ?? '';
        $lastName = $fullNameParts[1] ?? '';

        $buyer = new PaygreenModel\Buyer();
        $buyer->setReference(sprintf('cus_%s', $this->hashids8->encode($order->getCustomer()->getId())));
        $buyer->setEmail($order->getCustomer()->getEmailCanonical());
        $buyer->setFirstName(!empty($firstName) ? $firstName : 'N/A');
        $buyer->setLastName(!empty($lastName) ? $lastName : 'N/A');
        $buyer->setBillingAddress($address);

        return $buyer;
    }

    private function getEligibleAmounts(OrderInterface $order)
    {
        $ecommerceAmount = array_sum([
            $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT),
            $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT),
            $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT),
            $order->getAlcoholicItemsTotal(),
        ]);

        return [
            'food' => $order->getTotal() - $ecommerceAmount,
            'ecommerce' => $ecommerceAmount,
        ];
    }

    private function getCallbackUrl(string $route): string
    {
        $channel = $this->channelContext->getChannel();

        if ('app' === $channel->getCode()) {
            return sprintf('coopcycle:/%s', $this->urlGenerator->generate($route, referenceType: UrlGeneratorInterface::ABSOLUTE_PATH));
        }

        return $this->urlGenerator->generate($route, referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function refund(PaymentInterface $payment, $amount = null)
    {
        $this->authenticate();

        $details = $payment->getDetails();

        $this->paygreenClient->refundPaymentOrder($details['paygreen_payment_order_id'], $details['paygreen_operation_id'], $amount);
    }
}
