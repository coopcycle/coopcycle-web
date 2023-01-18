<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\StripePaymentMethodOutput;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateSetupIntentOrAttachPM
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
        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        if (!isset($body['payment_method_to_save'])) {
            throw new BadRequestHttpException('Mandatory parameters are missing');
        }

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        $this->stripeManager->configure();

        try {
            $intent = $this->stripeManager->createSetupIntent($payment, $body['payment_method_to_save']);

            // if payment method requires some extra action we can not save the payment method through the SetupIntent
            // because we want to avoid request an extra action to the client now
            if ($intent->status === 'requires_action') {
                // in this case we save the payment method data and when the payment is finally authorised
                // $gateway->authorize() we'll attach it to the customer
                $payment->setPaymentDataToSaveAndReuse($body['payment_method_to_save']);

                $this->entityManager->flush();
            }

        } catch (ApiErrorException $e) {

            throw new BadRequestHttpException($e->getMessage());
        }

        return [];
    }
}
