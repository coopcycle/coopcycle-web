<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentDetailsOutput;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentDetails
{
    private $stripeManager;
    private $edenredClient;

    public function __construct(
        StripeManager $stripeManager,
        EdenredClient $edenredClient)
    {
        $this->stripeManager = $stripeManager;
        $this->edenredClient = $edenredClient;
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

        if ($payment->isEdenredWithCard()) {
            $output->breakdown = $this->edenredClient->splitAmounts($data);
        }

        return $output;
    }
}
