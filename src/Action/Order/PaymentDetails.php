<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentDetailsOutput;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentDetails
{
    private $stripeManager;

    public function __construct(
        StripeManager $stripeManager,
        private GatewayResolver $gatewayResolver,
        private UrlGeneratorInterface $urlGenerator,
        private Hashids $hashids8)
    {
        $this->stripeManager = $stripeManager;
    }

    public function __invoke($data, Request $request)
    {
        $output = new PaymentDetailsOutput();

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        if (!$payment) {
            throw new BadRequestHttpException(sprintf('Order #%d has no payment', $data->getId()));
        }

        $gateway = $this->gatewayResolver->resolveForOrder($data);

        if ('paygreen' === $gateway) {

            $output->paygreenWebviewUrl = $this->urlGenerator->generate('paygreen_webview',
                ['hashId'=> $this->hashids8->encode($payment->getId())],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            return $output;
        }

        $this->stripeManager->configurePayment($payment);

        $output->stripeAccount = $payment->getStripeUserId();

        return $output;
    }
}
