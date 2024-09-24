<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Form\Checkout\CheckoutPayment;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Form\Order\AdhocOrderType;
use AppBundle\Service\OrderManager;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use phpcent\Client as CentrifugoClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Stripe;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}/pub", requirements={ "_locale": "%locale_regex%" })
 */
class PublicController extends AbstractController
{
    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/o/{hashid}", name="public_order")
     */
    public function orderAction($hashid, Request $request,
        EntityManagerInterface $objectManager,
        StripeManager $stripeManager,
        Hashids $hashids8)
    {
        $decoded = $hashids8->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $order = $this->orderRepository->find($id);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order #%d does not exist', $id));
        }

        if ($order->hasVendor()) {
            throw $this->createAccessDeniedException();
        }

        $this->denyAccessUnlessGranted('view_public', $order);

        $completedPayment = $order->getPayments()
            ->filter(fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_COMPLETED)
            ->first();

        $failedPayment = $order->getPayments()
            ->filter(fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_FAILED)
            ->first();

        $parameters = [
            'order' => $order,
            'completed_payment' => $completedPayment,
            'failed_payment' => $failedPayment,
        ];

        if (!$completedPayment) {

            $checkoutPayment = new CheckoutPayment($order);
            $paymentForm = $this->createForm(CheckoutPaymentType::class, $checkoutPayment);

            $paymentForm->handleRequest($request);
            if ($paymentForm->isSubmitted() && $paymentForm->isValid()) {

                $stripeToken = $paymentForm->get('stripePayment')->get('stripeToken')->getData();

                $lastPayment = $order->getPayments()
                    ->filter(fn (PaymentInterface $payment): bool =>
                        in_array($payment->getState(), [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW])
                    )
                    ->first();

                try {

                    if ($lastPayment->requiresUseStripeSDK()) {
                        $stripeManager->confirmIntent($lastPayment);
                    }

                    $stripeManager->capture($lastPayment);

                    $lastPayment->setState(PaymentInterface::STATE_COMPLETED);

                } catch (Stripe\Exception\ApiErrorException $e) {

                    $lastPayment->setLastError($e->getMessage());

                } finally {
                    $objectManager->flush();
                }

                return $this->redirectToRoute('public_order', [
                    'hashid' => $hashid
                ]);
            }

            $parameters['payment_form'] = $paymentForm->createView();
        }

        return $this->render('public/order.html.twig', $parameters);
    }

    /**
     * @Route("/d/{hashid}", name="public_delivery")
     */
    public function deliveryAction($hashid, Request $request,
        CentrifugoClient $centrifugoClient,
        Hashids $hashids8)
    {
        $decoded = $hashids8->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $delivery = $this->getDoctrine()->getRepository(Delivery::class)->find($id);

        if (null === $delivery) {
            throw $this->createNotFoundException(sprintf('Delivery #%d does not exist', $id));
        }

        $courier = null;
        if ($delivery->isAssigned()) {
            $courier = $delivery->getPickup()->getAssignedCourier();
        }

        $token = '';
        $channel = '';
        if ($delivery->isAssigned() && !$delivery->isCompleted()) {

            // Token expires 3 hours after expected completion
            $expiration = clone $delivery->getDropoff()->getDoneBefore();
            $expiration->modify('+3 hours');

            $channel = sprintf('%s_tracking#%s', $this->getParameter('centrifugo_namespace'), $courier->getUsername());
            $token = $centrifugoClient->generateConnectionToken($courier->getUsername(), $expiration->getTimestamp());
        }

        return $this->render('delivery/tracking.html.twig', [
            'delivery' => $delivery,
            'courier' => $courier,
            'centrifugo_token' => $token,
            'centrifugo_channel' => $channel,
        ]);
    }

    /**
     * @Route("/ado/{hashid}", name="public_adhoc_order")
     */
    public function adhocOrderAction($hashid, Request $request,
        EntityManagerInterface $objectManager,
        OrderManager $orderManager,
        Hashids $hashids8)
    {
        $order = $this->decodeOrderFromHashid($hashid, $hashids8);
        $payment = $order->getLastPayment();

        $form = $this->createForm(AdhocOrderType::class, $order);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $objectManager->flush();

            return $this->redirectToRoute('public_adhoc_order_payment', [
                'hashid' => $hashid,
            ]);
        }

        return $this->render('public/adhoc_order.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'payment' => $payment,
        ]);
    }

    /**
     * @Route("/ado/{hashid}/p", name="public_adhoc_order_payment")
     */
    public function adhocOrderPaymentAction($hashid, Request $request,
        EntityManagerInterface $objectManager,
        OrderManager $orderManager,
        Hashids $hashids8)
    {
        $order = $this->decodeOrderFromHashid($hashid, $hashids8);
        $payment = $order->getLastPayment();

        $form = $this->createForm(AdhocOrderType::class, $order, [
            'with_payment' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $orderManager->checkout($order, [
                'stripeToken' => $form->get('payment')->get('stripeToken')->getData()
            ]);

            $orderManager->accept($order);

            $objectManager->flush();

            return $this->redirectToRoute('public_adhoc_order', [
                'hashid' => $hashid,
            ]);
        }

        return $this->render('public/adhoc_order.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'payment' => $payment,
        ]);
    }

    private function decodeOrderFromHashid($hashid, Hashids $hashids8)
    {
        $decoded = $hashids8->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $order = $this->orderRepository->find($id);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order #%d does not exist', $id));
        }

        return $order;
    }
}
