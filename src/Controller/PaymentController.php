<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Form\Checkout\ChargeStripeSourceType;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    use OrderConfirmTrait;

    public function __construct(
        EntityManagerInterface $objectManager,
        OrderManager $orderManager,
        StripeManager $stripeManager,
        OrderTimeHelper $orderTimeHelper,
        string $secret)
    {
        $this->objectManager = $objectManager;
        $this->orderManager = $orderManager;
        $this->stripeManager = $stripeManager;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->secret = $secret;
    }

    private function handleChargeableSource(PaymentInterface $payment)
    {
        $order = $payment->getOrder();

        // @see https://stripe.com/docs/sources/giropay#source-expiration
        // A source must be used within six hours of becoming chargeable.
        // If it is not, its status is automatically transitioned to canceled
        // and your integration receives a source.canceled webhook event.
        // Additionally, pending sources are canceled after one hour if they are not used to authenticate a payment.

        // @see https://stripe.com/docs/sources/best-practices#cancellations-and-failures
        // Your source.chargeable handler should have created a charge immediately,
        // preventing the source from getting canceled

        $this->orderManager->checkout($order);

        $this->objectManager->flush();

        // Something went wrong when charging source
        if (PaymentInterface::STATE_FAILED === $payment->getState()) {
            throw new BadRequestHttpException(sprintf('Payment #%d could not be charged', $payment->getId()));
        }

        return $this->redirectToOrderConfirm($order);
    }

    /**
     * @Route("/payment/{hashId}/confirm", name="payment_confirm")
     */
    public function confirmAction($hashId, Request $request)
    {
        // @see https://stripe.com/docs/sources/giropay#customer-action
        // Stripe populates the redirect[return_url] with the following GET parameters when returning your customer to your website:
        // - source: a string representing the original ID of the Source object
        // - livemode: indicates if this is a live payment, either true or false
        // - client_secret: used to confirm that the returning customer is the same one
        //                  who triggered the creation of the source (source IDs are not considered secret)

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

        $clientSecret = $request->query->get('client_secret');
        $sourceId = $request->query->get('source');

        // TODO Compare sources

        if ($payment->getSourceClientSecret() !== $clientSecret) {
            throw new BadRequestHttpException(sprintf('Client secret for payment with id "%d" does not match', $paymentId));
        }

        $order = $payment->getOrder();

        $this->stripeManager->configure();

        $stripeAccount = $payment->getStripeUserId();
        $stripeOptions = [];

        if (null !== $stripeAccount) {
            $stripeOptions['stripe_account'] = $stripeAccount;
        }

        $source = Stripe\Source::retrieve($sourceId, $stripeOptions);

        // We could check if the source is already chargeable,
        // but let's display a fancy confirmation page
        // if ('chargeable' === $source->status) {
        //     return $this->handleChargeableSource($payment);
        // }

        $form = $this->createForm(ChargeStripeSourceType::class, $order);

        $form->handleRequest($request);

        // The source will be polled until chargeable
        if ($form->isSubmitted() && $form->isValid()) {

            return $this->handleChargeableSource($payment);
        }

        return $this->render('order/wait_for_payment.html.twig', [
            'order' => $order,
            'shipping_range' => $this->orderTimeHelper->getShippingTimeRange($order),
            'source_id' => $sourceId,
            'client_secret' => $clientSecret,
            'stripe_options' => $stripeOptions,
            'form' => $form->createView(),
        ]);
    }
}
