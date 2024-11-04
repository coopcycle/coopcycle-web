<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentDetailsOutput;
use AppBundle\Payment\GatewayResolver;
use Hashids\Hashids;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentDetails
{
    public function __construct(
        private GatewayResolver $gatewayResolver,
        private UrlGeneratorInterface $urlGenerator,
        private Hashids $hashids8)
    {}

    public function __invoke($data, Request $request)
    {
        $output = new PaymentDetailsOutput();

        $payments = $data->getPayments()->filter(
            fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_CART);

        $cardPayment = $payments->filter(
            fn (PaymentInterface $payment): bool => $payment->getMethod()->getCode() === 'CARD')->first();

        if ($cardPayment) {
            // We keep this for backward compatibility,
            // but this should not be at top level
            $output->stripeAccount = $cardPayment->getStripeUserId();

            $gateway = $this->gatewayResolver->resolveForOrder($data);

            if ('paygreen' === $gateway) {

                $output->paygreenWebviewUrl = $this->urlGenerator->generate('paygreen_webview',
                    ['hashId'=> $this->hashids8->encode($cardPayment->getId())],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                return $output;
            }
        }

        $output->payments = $payments;

        return $output;
    }
}
