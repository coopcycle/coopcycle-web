<?php

namespace AppBundle\Payment;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Refund;
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

                if ($payment->hasToSavePaymentMethod()) {
                    $this->stripeManager->attachPaymentMethodToCustomer($payment);
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

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        $refund = $payment->addRefund($amount);

        $method = $payment->getMethod();

        // This means the whole amount has been paid with Edenred
        if ($method && 'EDENRED' === $method->getCode()) {

            $this->edenred->refund($payment, $payment->getAmount());

        // In this case, we refund by priority with credit card,
        // and also with Edenred if needed
        } elseif ($payment->isEdenredWithCard()) {

            switch ($this->resolver->resolve()) {
                case 'mercadopago':
                    // TODO Implement
                    throw new \RuntimeException('Refunding with MercadoPago is not implemented yet');
                case 'stripe':
                default:

                    $cardAmount     = $payment->getRefundableAmountForMethod('CARD', $amount);
                    $edenredAmount  = $payment->getRefundableAmountForMethod('EDENRED', $amount);

                    $stripeRefund = $this->stripeManager->refund($payment, $cardAmount);
                    $payment->addStripeRefund($stripeRefund);
                    $refund->setData(['stripe_refund_id' => $stripeRefund->id]);

                    if ($edenredAmount > 0) {
                        $this->edenred->refund($payment, $edenredAmount);
                        $refund->setData(['edenred_transaction_id' => $payment->getEdenredAuthorizationId()]);
                    }
            }

        } else {
            $stripeRefund = $this->stripeManager->refund($payment, $amount);
            $payment->addStripeRefund($stripeRefund);
            $refund->setData(['stripe_refund_id' => $stripeRefund->id]);
        }

        return $refund;
    }
}
