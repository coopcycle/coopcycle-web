<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentDetailsOutput;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentDetails
{
    private $stripeManager;

    public function __construct(
        StripeManager $stripeManager)
    {
        $this->stripeManager = $stripeManager;
    }

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
        }

        $output->payments = $payments;

        return $output;
    }
}
