<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\StripePaymentMethodOutput;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CloneStripePayment
{
    private $entityManager;
    private $stripeManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        StripeManager $stripeManager)
    {
        $this->entityManager = $entityManager;
        $this->stripeManager = $stripeManager;
    }

    public function __invoke($data, Request $request)
    {
        if (!$request->attributes->get('paymentMethodId')) {
            throw new BadRequestHttpException('Mandatory parameters are missing');
        }

        $payment = $data->getLastPaymentByMethod('CARD', PaymentInterface::STATE_CART);

        try {
            $payment->setPaymentMethod($request->attributes->get('paymentMethodId'));

            $clonedPaymentMethod = $this->stripeManager->clonePaymentMethodToConnectedAccount($payment);

            $this->entityManager->flush();

        } catch (ApiErrorException $e) {

            throw new BadRequestHttpException($e->getMessage());
        }

        return new StripePaymentMethodOutput($clonedPaymentMethod);
    }
}
