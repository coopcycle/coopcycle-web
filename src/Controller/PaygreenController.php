<?php

namespace AppBundle\Controller;

use AppBundle\Service\PaygreenManager;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Paygreen\Sdk\Payment\V3\Model as PaygreenModel;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @see https://developers.paygreen.fr/reference/post_create_payment_order
 * @see https://developers.paygreen.fr/docs/payment#payment-orders
 */
class PaygreenController extends AbstractController
{
    public function __construct(
        private Hashids $hashids8,
        private EntityManagerInterface $entityManager,
        private PaygreenManager $paygreenManager,
        private LoggerInterface $logger)
    {}

    /**
     * @Route("/paygreen/create-payment-order/{hashId}", name="paygreen_create_payment_order")
     */
    public function createPaymentOrderAction($hashId, Request $request)
    {
        try {

            $instrument = $request->get('instrument');

            [ $paymentOrderId, $objectSecret ] = $this->getViewData($hashId, $instrument);

            return new JsonResponse([
                'id' => $paymentOrderId,
                'object_secret' => $objectSecret,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * @Route("/paygreen/{hashId}/webview", name="paygreen_webview")
     */
    public function webviewAction($hashId)
    {
        [ $paymentOrderId, $objectSecret ] = $this->getViewData($hashId);

        return $this->render('payment/paygreen_webview.html.twig', [
            'payment_order_id' => $paymentOrderId,
            'object_secret' => $objectSecret,
        ]);
    }

    private function getViewData($hashId, $instrument = null): array
    {
        $decoded = $this->hashids8->decode($hashId);
        if (count($decoded) !== 1) {
            $this->logger->warning(sprintf('Payment with hash "%s" does not exist', $hashId));

            throw new \Exception(sprintf('Payment with hash "%s" does not exist', $hashId));
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment with id "%d" does not exist', $paymentId), ['hash' => $hashId]);

            throw new \Exception(sprintf('Payment with id "%d" does not exist', $paymentId));
        }
        // var_dump($instrument);

        $this->paygreenManager->createPaymentOrder($payment, $instrument);

        $this->entityManager->flush();

        return [
            $payment->getPaygreenPaymentOrderId(),
            $payment->getPaygreenObjectSecret()
        ];
    }

    /**
     * @Route("/paygreen/order/buyer", name="paygreen_order_buyer")
     */
    public function getPaygreenOrderBuyer(CartContextInterface $cartContext)
    {
        $order = $cartContext->getCart();

        if (null === $order || !$order->hasVendor()) {

            // TODO HTTP 400
        }

        if (null === $order->getCustomer()) {

            // TODO HTTP 400
        }

        $buyer = $this->paygreenManager->getBuyerForOrder($order);

        return new JsonResponse([
            'buyer' => $buyer->getId(),
        ]);
    }

    /**
     * @Route("/paygreen/payment-orders/{id}", name="paygreen_payment_order")
     */
    public function getPaygreenPaymentOrder($id)
    {
        $po = $this->paygreenManager->getPaymentOrder($id);

        // Conecs ins_8c677353439b474fb250851c361cf65e
        // Swile  ins_fa6054594cab453a963c56cb2561dcd8
        // $ins = $this->paygreenManager->getInstrument('ins_fa6054594cab453a963c56cb2561dcd8');

        // dd($ins);
        dd($po);


    }
}
