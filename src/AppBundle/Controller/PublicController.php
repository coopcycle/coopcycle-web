<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryOrder;
use AppBundle\Entity\DeliveryOrderItem;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\StripePaymentType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/{_locale}/pub", requirements={ "_locale": "%locale_regex%" })
 */
class PublicController extends Controller
{
    /**
     * @Route("/d/{number}", name="public_delivery")
     * @Template
     */
    public function deliveryAction($number, Request $request)
    {
        $settingsManager = $this->get('coopcycle.settings_manager');
        $stateMachineFactory = $this->get('sm.factory');

        Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));

        $order = $this->get('sylius.repository.order')->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        $deliveryOrderItem = $this->getDoctrine()
            ->getRepository(DeliveryOrderItem::class)
            ->findOneByOrderItem($order->getItems()->get(0));

        $delivery = $deliveryOrderItem->getDelivery();

        $stripePayment = $this->getDoctrine()
            ->getRepository(StripePayment::class)
            ->findOneByOrder($order);

        $stripePaymentStateMachine = $stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

        $form = $this->createForm(StripePaymentType::class, $stripePayment);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $stripeToken = $form->get('stripeToken')->getData();

            $charge = Stripe\Charge::create([
              'amount' => $stripePayment->getAmount(),
              'currency' => strtolower($stripePayment->getCurrencyCode()),
              'description' => sprintf('Order %s', $order->getNumber()),
              'metadata' => [
                'order_id' => $order->getId()
              ],
              'source' => $stripeToken,
            ]);

            $stripePayment->setCharge($charge->id);
            $stripePaymentStateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);

            $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();

            return $this->redirectToRoute('public_delivery', ['number' => $number]);
        }

        return [
            'order' => $order,
            'delivery' => $delivery,
            'stripe_payment' => $stripePayment,
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/i/{number}", name="public_invoice")
     * @Template
     */
    public function invoiceAction($number, Request $request)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        $deliveryOrder = $this->getDoctrine()
            ->getRepository(DeliveryOrder::class)
            ->findOneByOrder($order);

        $deliveryOrderItem = $this->getDoctrine()
            ->getRepository(DeliveryOrderItem::class)
            ->findOneByOrderItem($order->getItems()->get(0));

        $user = $deliveryOrder->getUser();
        $delivery = $deliveryOrderItem->getDelivery();

        $html = $this->renderView('@App/Pdf/delivery.html.twig', [
            'order' => $order,
            'delivery' => $delivery,
            'customer' => $user
        ]);

        return new Response($this->get('knp_snappy.pdf')->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
