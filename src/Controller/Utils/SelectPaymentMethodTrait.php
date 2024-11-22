<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Sylius\Payment\Context as PaymentContext;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

trait SelectPaymentMethodTrait
{
    protected function selectPaymentMethodForOrder(
        OrderInterface $order,
        Request $request,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        EntityManagerInterface $entityManager,
        NormalizerInterface $normalizer,
        PaymentContext $paymentContext,
        OrderProcessorInterface $orderPaymentProcessor,
        Hashids $hashids8): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['method'])) {

            return new JsonResponse(['message' => 'No payment method found in request'], 400);
        }

        $code = strtoupper($data['method']);

        $paymentMethod = $paymentMethodRepository->findOneByCode($code);

        if (null === $paymentMethod) {

            return new JsonResponse(['message' => 'Payment method does not exist'], 404);
        }

        // The "CASH_ON_DELIVERY" payment method may not be enabled,
        // however if it's enabled at shop level, it is allowed
        $bypass = $code === 'CASH_ON_DELIVERY' && $order->supportsCashOnDelivery();

        if (!$paymentMethod->isEnabled() && !$bypass) {

            return new JsonResponse(['message' => 'Payment method is not enabled'], 400);
        }

        $paymentContext->setMethod($code);

        $orderPaymentProcessor->process($order);

        $entityManager->flush();

        $payments = array_values($order->getPayments()->toArray());

        $paymentStates = [
            PaymentInterface::STATE_CART,
            PaymentInterface::STATE_NEW,
        ];

        $cardPayment = $order->getPayments()->filter(fn (PaymentInterface $payment): bool =>
            $payment->getMethod()->getCode() === 'CARD' && in_array($payment->getState(), $paymentStates)
        )->first();

        $stripe = [];
        if ($cardPayment) {
            $hashId = $hashids8->encode($cardPayment->getId());
            $stripe = [
                'createPaymentIntentURL' => $this->generateUrl('stripe_create_payment_intent', ['hashId' => $hashId]),
                'account' => $cardPayment->getStripeUserId(),
                'clonePaymentMethodToConnectedAccountURL' => $this->generateUrl('stripe_clone_payment_method', ['hashId' => $hashId]),
                'createSetupIntentOrAttachPMURL' => $this->generateUrl('stripe_create_setup_intent_or_attach_pm', ['hashId' => $hashId]),
            ];
        }

        return new JsonResponse([
            'payments' => $normalizer->normalize($payments, 'json', ['groups' => ['payment']]),
            'stripe' => $stripe,
        ]);
    }
}
