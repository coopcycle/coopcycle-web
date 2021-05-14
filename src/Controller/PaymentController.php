<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Service\OrderManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Stripe;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    use OrderConfirmTrait;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrderManager $orderManager,
        StripeManager $stripeManager,
        OrderTimeHelper $orderTimeHelper,
        string $secret)
    {
        $this->entityManager = $entityManager;
        $this->orderManager = $orderManager;
        $this->stripeManager = $stripeManager;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->secret = $secret;
    }

    /**
     * @Route("/payment/{hashId}/confirm", name="payment_confirm")
     */
    public function confirmAction($hashId, Request $request)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Payment with hash "%s" does not exist', $hashId));
        }

        $paymentId = current($decoded);

        $payment = $this->getDoctrine()
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {
            throw new BadRequestHttpException(sprintf('Payment with id "%d" does not exist', $paymentId));
        }

        /** @deprecated */
        if ($request->query->has('source') && $request->query->has('client_secret')) {

            throw new BadRequestHttpException('Stripe sources are deprecated');
        }

        $paymentIntent = $request->query->get('payment_intent');
        $paymentIntentClientSecret = $request->query->get('payment_intent_client_secret');

        if ($payment->getPaymentIntent() !== $paymentIntent) {
            throw new BadRequestHttpException(sprintf('Payment Intent for payment with id "%d" does not match', $payment->getId()));
        }

        $order = $payment->getOrder();

        $this->stripeManager->configure();

        $stripeAccount = $payment->getStripeUserId();
        $stripeOptions = [];

        if (null !== $stripeAccount) {
            $stripeOptions['stripe_account'] = $stripeAccount;
        }

        // We could check if the payment intent has already succeeded,
        // but let's display a fancy confirmation page

        if ($request->isMethod('POST')) {

            // Double-check the intent status
            $intent = Stripe\PaymentIntent::retrieve(
                $request->query->get('payment_intent'),
                $stripeOptions
            );

            if ('succeeded' === $intent->status) {

                // If the "payment_intent.succeeded" webhook has not been called yet,
                // we complete the checkout here
                if (PaymentInterface::STATE_PROCESSING === $payment->getState()) {
                    $this->orderManager->checkout($order);
                    $this->entityManager->flush();
                }

                return $this->redirectToOrderConfirm($order);
            }
        }

        return $this->render('order/wait_for_payment.html.twig', [
            'order' => $order,
            'shipping_range' => $this->orderTimeHelper->getShippingTimeRange($order),
            'client_secret' => $paymentIntentClientSecret,
            'stripe_options' => $stripeOptions,
        ]);
    }
}
