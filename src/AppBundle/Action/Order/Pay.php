<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Pay
{
    private $dataManager;
    private $doctrine;

    public function __construct(OrderManager $dataManager, ManagerRegistry $doctrine)
    {
        $this->orderManager = $dataManager;
        $this->doctrine = $doctrine;
    }

    public function __invoke($data, Request $request)
    {
        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        if (!isset($body['stripeToken'])) {
            throw new BadRequestHttpException('Stripe token is missing');
        }

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        $this->orderManager->checkout($data, $body['stripeToken']);
        $this->doctrine->getManagerForClass(Order::class)->flush();

        if (PaymentInterface::STATE_FAILED === $payment->getState()) {
            throw new BadRequestHttpException($payment->getLastError());
        }

        return $data;
    }
}
