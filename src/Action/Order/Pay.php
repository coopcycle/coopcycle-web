<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\StripeOutput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use AppBundle\Service\StripeManager;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stripe\Exception\ApiErrorException;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Pay
{
    private $entityManager;
    private $stripeManager;
    private $orderNumberAssigner;
    private $cashEnabled;

    public function __construct(
        OrderManager $dataManager,
        EntityManagerInterface $entityManager,
        StripeManager $stripeManager,
        OrderNumberAssignerInterface $orderNumberAssigner,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        GatewayResolver $gatewayResolver,
        MercadopagoManager $mercadopagoManager,
        bool $cashEnabled = false,
        LoggerInterface $logger = null)
    {
        $this->orderManager = $dataManager;
        $this->entityManager = $entityManager;
        $this->stripeManager = $stripeManager;
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->gatewayResolver = $gatewayResolver;
        $this->mercadopagoManager = $mercadopagoManager;
        $this->cashEnabled = $cashEnabled;
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke($data, Request $request): OrderInterface|StripeOutput
    {
        $body = $request->toArray();

        if ($data->isFree()) {
            $this->orderManager->checkout($data);
            $this->entityManager->flush();

            return $data;
        }

        if (!isset($body['paymentMethodId']) && !isset($body['paymentIntentId']) && !isset($body['cashOnDelivery'])) {
            throw new BadRequestHttpException('Mandatory parameters are missing');
        }

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        if (isset($body['paymentMethodId']) && !isset($body['paymentIntentId'])) {
            if ('mercadopago' === $this->gatewayResolver->resolve()) {
                return $this->handleMercadopagoPayment($data, $payment, $body);
            } else {
                // This is the 1st step of a Stripe payment, it will create a payment intent
                // Then this endpoint will be called a 2nd time to confirm the intent
                return $this->handleStripePaymentIntent($data, $payment, $body);
            }
        }

        if (isset($body['cashOnDelivery']) && true === $body['cashOnDelivery']) {
            return $this->handleCashOnDelivery($data, $payment, $body);
        }

        // This is the 2nd step of a Stripe payment, to confirm the payment intent
        if (isset($body['paymentIntentId'])) {

            $this->orderManager->checkout($data, $body['paymentIntentId']);
            $this->entityManager->flush();

            if (PaymentInterface::STATE_FAILED === $payment->getState()) {
                throw new BadRequestHttpException($payment->getLastError());
            }

            return $data;
        }

        return $data;
    }

    private function handleStripePaymentIntent(OrderInterface $data, PaymentInterface $payment, array $body): StripeOutput
    {
        // Assign order number now because it is needed for Stripe
        $this->orderNumberAssigner->assignNumber($data);

        try {

            $payment->setPaymentMethod($body['paymentMethodId']);

            $saveCard = isset($body['saveCard']) ? $body['saveCard'] : false;

            $intent = $this->stripeManager->createIntent($payment, $saveCard);
            $payment->setPaymentIntent($intent);

            $this->entityManager->flush();

        } catch (ApiErrorException $e) {

            throw new BadRequestHttpException($e->getMessage());
        }

        $response = new StripeOutput();

        if ($payment->requiresUseStripeSDK()) {

            $this->logger->info(
                sprintf('Order #%d | Payment Intent requires action "%s"', $data->getId(), $payment->getPaymentIntentNextAction())
            );

            $response->requiresAction = true;
            $response->paymentIntentClientSecret = $payment->getPaymentIntentClientSecret();

        // When the status is "succeeded", it means we captured automatically
        // When the status is "requires_capture", it means we separated authorization and capture
        } elseif ('succeeded' === $payment->getPaymentIntentStatus() || $payment->requiresCapture()) {

            $this->logger->info(
                sprintf('Order #%d | Payment Intent status is "%s"', $data->getId(), $payment->getPaymentIntentStatus())
            );

            // The payment didn’t need any additional actions and completed!
            // Handle post-payment fulfillment
            $response->requiresAction = false;
            $response->paymentIntentId = $payment->getPaymentIntent();

        } else {
            throw new BadRequestHttpException('Invalid PaymentIntent status');
        }

        return $response;
    }

    private function handleMercadopagoPayment(OrderInterface $data, PaymentInterface $payment, array $body): OrderInterface
    {
        $payment->setMercadopagoPaymentId($body['paymentId']);

        $mpPayment = $this->mercadopagoManager->getPayment($payment);

        if (!$mpPayment) {
            throw new BadRequestHttpException(sprintf('Mercadopago payment with id %s not found', $body['paymentId']));
        } else if ($mpPayment->status !== 'approved') {
            throw new BadRequestHttpException(sprintf('Mercadopago payment with id %s is not approved. Status: %s', $body['paymentId'], $mpPayment->status));
        }

        $payment->setMercadopagoPaymentStatus($mpPayment->status);
        $payment->setMercadopagoPaymentMethod($mpPayment->payment_method_id);
        $payment->setMercadopagoInstallments($mpPayment->installments);

        $paymentMethod = $this->paymentMethodRepository->findOneByCode($body['paymentMethodId']);
        $payment->setMethod($paymentMethod);

        $this->orderManager->checkout($data);
        $this->entityManager->flush();

        if (PaymentInterface::STATE_FAILED === $payment->getState()) {
            throw new BadRequestHttpException($payment->getLastError());
        }

        return $data;
    }

    private function handleCashOnDelivery(OrderInterface $data, PaymentInterface $payment, array $body): OrderInterface
    {
        if (!$this->cashEnabled && !$data->supportsCashOnDelivery()) {
            throw new BadRequestHttpException('Cash on delivery is not enabled');
        }

        $paymentMethod = $this->paymentMethodRepository->findOneByCode('CASH_ON_DELIVERY');
        if (null === $paymentMethod) {
            throw new BadRequestHttpException('Payment method "CASH_ON_DELIVERY" not found');
        }

        $payment->setMethod($paymentMethod);

        $this->orderManager->checkout($data);
        $this->entityManager->flush();

        if (PaymentInterface::STATE_FAILED === $payment->getState()) {
            throw new BadRequestHttpException($payment->getLastError());
        }

        return $data;
    }
}
