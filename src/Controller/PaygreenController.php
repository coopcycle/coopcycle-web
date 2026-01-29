<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Service\PaygreenManager;
use AppBundle\Service\OrderManager;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Hashids\Hashids;
use Paygreen\Sdk\Payment\V3\Model as PaygreenModel;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see https://developers.paygreen.fr/reference/post_create_payment_order
 * @see https://developers.paygreen.fr/docs/payment#payment-orders
 */
class PaygreenController extends AbstractController
{
    use OrderConfirmTrait;

    public function __construct(
        private Hashids $hashids8,
        private EntityManagerInterface $entityManager,
        private PaygreenManager $paygreenManager)
    {}

    #[Route(path: '/paygreen/create-payment-order/{hashId}', name: 'paygreen_create_payment_order')]
    public function createPaymentOrderAction($hashId, Request $request)
    {
        try {

            [ $paymentOrderId, $objectSecret, $hostedPaymentUrl ] = $this->getViewData($hashId);

            return new JsonResponse([
                'id' => $paymentOrderId,
                'object_secret' => $objectSecret,
                'hosted_payment_url' => $hostedPaymentUrl,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }
    }

    private function getViewData($hashId): array
    {
        $decoded = $this->hashids8->decode($hashId);
        if (count($decoded) !== 1) {
            throw new \Exception(sprintf('Payment with hash "%s" does not exist', $hashId));
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {
            throw new \Exception(sprintf('Payment with id "%d" does not exist', $paymentId));
        }

        $this->paygreenManager->createPaymentOrder($payment);

        $this->entityManager->flush();

        return [
            $payment->getPaygreenPaymentOrderId(),
            $payment->getPaygreenObjectSecret(),
            $payment->getPaygreenHostedPaymentUrl(),
        ];
    }

    #[Route(path: '/paygreen/return', name: 'paygreen_return')]
    public function return(OrderManager $orderManager, Request $request)
    {
        $paymentOrderId = $request->query->get('po_id');

        $qb = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->join(PaymentInterface::class, 'p', Expr\Join::WITH, 'p.order = o.id')
            ->andWhere('JSON_GET_FIELD_AS_TEXT(p.details, \'paygreen_payment_order_id\') = :payment_order_id')
            ->setParameter('payment_order_id', $paymentOrderId)
            ;

        $order = $qb->getQuery()->getOneOrNullResult();

        if (null === $order) {
            return $this->redirectToRoute('order_payment');
        }

        // There is a "status" query parameter,
        // but do not trust it as it could be changed
        $po = $this->paygreenManager->getPaymentOrder($paymentOrderId);
        if (!in_array($po['status'], ['payment_order.authorized', 'payment_order.successed', 'payment_order.completed'])) {
            return $this->redirectToRoute('order_payment');
        }

        $payments = $order->getPayments();
        foreach ($payments as $payment) {
            $order->removePayment($payment);
        }

        // We make our payments match with payments made on Paygreen
        foreach ($this->paygreenManager->getPaymentsFromPaymentOrder($paymentOrderId) as $payment) {
            $order->addPayment($payment);
        }

        $orderManager->checkout($order);

        $this->entityManager->flush();

        return $this->redirectToOrderConfirm($order);
    }

    #[Route(path: '/paygreen/cancel', name: 'paygreen_cancel')]
    public function cancel()
    {
        return $this->redirectToRoute('order_payment');
    }
}
