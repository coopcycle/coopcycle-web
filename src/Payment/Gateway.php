<?php

namespace AppBundle\Payment;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Message\RetrieveStripeFee;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Service\StripeManager;
use Omnipay\Common\Message\ResponseInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class Gateway
{
    private $resolver;
    private $stripeManager;
    private $mercadopagoManager;

    public function __construct(
        GatewayResolver $resolver,
        StripeManager $stripeManager,
        MercadopagoManager $mercadopagoManager,
        EdenredClient $edenred)
    {
        $this->resolver = $resolver;
        $this->stripeManager = $stripeManager;
        $this->mercadopagoManager = $mercadopagoManager;
        $this->edenred = $edenred;
    }

    public function authorize(PaymentInterface $payment, array $context = []): ResponseInterface
    {
        $method = $payment->getMethod();

        // This means the whole amount will be paid with Edenred
        if ($method && 'EDENRED' === $method->getCode()) {
            $authorizationId = $this->edenred->authorizeTransaction($payment);
            $payment->setEdenredAuthorizationId($authorizationId);

            return new StripeResponse([]);
        }

        switch ($this->resolver->resolve()) {
            case 'mercadopago':

                $payment->setStripeToken($context['token']);

                $p = $this->mercadopagoManager->authorize($payment);

                $payment->setCharge($p->id);

                return new MercadopagoResponse($p);
            case 'stripe':
            default:

                $paymentIntent = $payment->getPaymentIntent();

                if (!$payment->isGiropay() && $paymentIntent !== $context['token']) {
                    throw new \Exception('Payment Intent mismatch');
                }

                if ($payment->requiresUseStripeSDK()) {
                    $this->stripeManager->confirmIntent($payment);
                }

                if ($payment->isEdenredWithCard()) {
                    $authorizationId = $this->edenred->authorizeTransaction($payment);
                    $payment->setEdenredAuthorizationId($authorizationId);
                }

                return new StripeResponse([]);
        }
    }

    public function capture(PaymentInterface $payment): ResponseInterface
    {
        $method = $payment->getMethod();

        // This means the whole amount has been paid with Edenred
        if ($method && 'EDENRED' === $method->getCode()) {
            $captureId = $this->edenred->captureTransaction($payment);
            $payment->setEdenredCaptureId($captureId);

            return new StripeResponse([]);
        }

        switch ($this->resolver->resolve()) {
            case 'mercadopago':
                $this->mercadopagoManager->capture($payment);

                return new MercadopagoResponse([]);
            case 'stripe':
            default:

                $this->stripeManager->capture($payment);

                if ($payment->isEdenredWithCard()) {
                    $captureId = $this->edenred->captureTransaction($payment);
                    $payment->setEdenredCaptureId($captureId);
                }

                return new StripeResponse([]);
        }
    }
}
