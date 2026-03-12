<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\PaymentRefundInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly OrderManager $orderManager,
        private readonly GatewayResolver $gatewayResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param PaymentRefundInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Order */
        $order = $this->provider->provide($operation, $uriVariables, $context);

        // If the order contains multiple payments,
        // client must use the /api/payments/{id}/refund endpoint
        // Except for PayGreen...
        if (count($order->getPayments()) > 1) {

            $payment = $order->getLastPayment(Payment::STATE_COMPLETED);
            $gateway = $this->gatewayResolver->resolveForPayment($payment);

            if ('paygreen' === $gateway) {
                // Even if there are multiple payments,
                // for PayGreen the payment order will be the same
                // We can refund any of the payments
                $this->orderManager->refundPayment($order->getLastPayment(Payment::STATE_COMPLETED), $order->getTotal(), $data->liableParty, $data->comments);
                $this->entityManager->flush();

                return $order;
            }

            throw new BadRequestHttpException('Order contains more than one payment, refund each payment individually');
        }

        if (null === $payment) {
            throw new BadRequestHttpException('Order does not contain any refundable payment');
        }

        $this->orderManager->refundPayment($payment, $order->getTotal(), $data->liableParty, $data->comments);
        $this->entityManager->flush();

        return $order;
    }
}
