<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\PaymentRefundInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly OrderManager $orderManager,
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
        if (count($order->getPayments()) > 1) {
            throw new BadRequestHttpException('Order contains more than one payment, refund each payment individually');
        }

        $payment = $order->getLastPayment(Payment::STATE_COMPLETED);

        if (null === $payment) {
            throw new BadRequestHttpException('Order does not contain any refundable payment');
        }

        $this->orderManager->refundPayment($payment, $order->getTotal(), $data->liableParty, $data->comments);
        $this->entityManager->flush();

        return $order;
    }
}
