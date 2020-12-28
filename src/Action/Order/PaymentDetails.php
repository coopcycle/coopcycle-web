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

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        if (!$payment) {
            throw new BadRequestHttpException(sprintf('Order #%d has no payment', $data->getId()));
        }

        $this->stripeManager->configurePayment($payment);

        $output->stripeAccount = $payment->getStripeUserId();

        return $output;
    }
}
