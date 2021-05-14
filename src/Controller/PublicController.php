<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Form\StripePaymentType;
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
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->getParameter('secret'), 8);
        $decoded = $hashids->decode($hashid);

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

        $lastPayment = $order->getLastPayment();

        $parameters = [
            'order' => $order,
            'last_payment' => $lastPayment,
        ];

        $paymentStates = [
            PaymentInterface::STATE_CART,
            PaymentInterface::STATE_NEW,
        ];

        if (in_array($lastPayment->getState(), $paymentStates)) {

            $paymentForm = $this->createForm(StripePaymentType::class, $lastPayment);

            $paymentForm->handleRequest($request);
            if ($paymentForm->isSubmitted() && $paymentForm->isValid()) {

                $stripeToken = $paymentForm->get('stripeToken')->getData();

                try {

                    $stripeManager->configure();

                    if ($lastPayment->requiresUseStripeSDK()) {
                        $stripeManager->confirmIntent($lastPayment);
                    }

                    $stripeManager->capture($lastPayment);

                    $lastPayment->setState(PaymentInterface::STATE_COMPLETED);

                } catch (Stripe\Exception\ApiErrorException $e) {

                    $lastPayment->setLastError($e->getMessage());
                    // TODO Create another payment

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
        CentrifugoClient $centrifugoClient)
    {
        $hashids = new Hashids($this->getParameter('secret'), 8);

        $decoded = $hashids->decode($hashid);

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
}
