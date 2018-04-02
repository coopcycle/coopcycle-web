<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\DeliveryAddressType;
use AppBundle\Form\StripePaymentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/order")
 */
class OrderController extends Controller
{
    /**
     * @Route("/", name="order")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $cart = $this->get('sylius.context.cart')->getCart();

        // TODO Check if cart is empty

        $deliveryAddress = $cart->getShippingAddress();

        $form = $this->createForm(DeliveryAddressType::class, $deliveryAddress);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $deliveryAddress = $form->getData();

            $this->getDoctrine()->getManagerForClass(Address::class)->flush();

            return $this->redirectToRoute('order_payment');
        }

        return array(
            'order' => $cart,
            'form' => $form->createView(),
            'restaurant' => $cart->getRestaurant(),
            'deliveryAddress' => $deliveryAddress,
        );
    }

    /**
     * @Route("/payment", name="order_payment")
     * @Template()
     */
    public function paymentAction(Request $request)
    {
        $stateMachineFactory = $this->get('sm.factory');
        $settingsManager = $this->get('coopcycle.settings_manager');

        $order = $this->get('sylius.context.cart')->getCart();

        $stripePayment = $this->getDoctrine()
            ->getRepository(StripePayment::class)
            ->findOneByOrder($order);

        if (null === $stripePayment) {
            $stripePayment = StripePayment::create($order);

            $this->getDoctrine()->getManagerForClass(StripePayment::class)->persist($stripePayment);
            $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();
        }

        $form = $this->createForm(StripePaymentType::class);

        $parameters =  [
            'order' => $order,
            'deliveryAddress' => $order->getShippingAddress(),
            'restaurant' => $order->getRestaurant(),
            'form' => $form->createView(),
        ];

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // Create order, to generate a number
            $order->setCustomer($this->getUser());
            $orderStateMachine = $stateMachineFactory->get($order, OrderTransitions::GRAPH);
            $orderStateMachine->apply(OrderTransitions::TRANSITION_CREATE);
            $this->get('sylius.manager.order')->flush();

            // Authorize payment
            $apiKey = $settingsManager->get('stripe_secret_key');
            Stripe\Stripe::setApiKey($apiKey);

            $stripePaymentStateMachine = $stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

            try {

                $stripeToken = $form->get('stripeToken')->getData();

                $charge = Stripe\Charge::create(array(
                    'amount' => $order->getTotal(),
                    'currency' => 'eur',
                    'source' => $stripeToken,
                    'description' => sprintf('Order %s', $order->getNumber()),
                    // To authorize a payment without capturing it,
                    // make a charge request that also includes the capture parameter with a value of false.
                    // This instructs Stripe to only authorize the amount on the customer’s card.
                    'capture' => false,
                ));

                $stripePayment->setCharge($charge->id);

                $stripePaymentStateMachine->apply(PaymentTransitions::TRANSITION_CREATE);

            } catch (\Exception $e) {

                $stripePaymentStateMachine->apply(PaymentTransitions::TRANSITION_FAIL);

                return array_merge($parameters, [
                    'error' => $e->getMessage()
                ]);

            } finally {
                $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();
            }

            $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
            $request->getSession()->remove($sessionKeyName);

            return $this->redirectToRoute('profile_order', array('id' => $order->getNumber()));
        }

        return $parameters;
    }

    /**
     * @Route("/public/{uuid}", name="order_public")
     * @Template("@App/Order/public.html.twig")
     * @param Request $request
     *
     */
    public function orderPublic($uuid, Request $request)
    {
        $orders = $this->getDoctrine()
            ->getRepository(Order::class)->findBy(['uuid' => $uuid]);

        if (count($orders) !== 1) {
            return $this->redirectToRoute('redirect_to_locale');
        }
        else {
            $order = array_pop($orders);
        }

        $orderEvents = [];
        foreach ($order->getEvents() as $event) {
            $orderEvents[] = [
                'status' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        $deliveryEvents = [];
        foreach ($order->getDelivery()->getEvents() as $event) {
            $deliveryEvents[] = [
                'status' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        return array(
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld'),
            'order_events_json' => $this->get('serializer')->serialize($orderEvents, 'json'),
            'delivery_events_json' => $this->get('serializer')->serialize($deliveryEvents, 'json'),
            'layout' => 'AppBundle::base.html.twig'
        );
    }
}
