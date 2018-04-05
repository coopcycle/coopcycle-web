<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\DeliveryAddressType;
use AppBundle\Form\StripePaymentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\Model\PaymentInterface;
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

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $form = $this->createForm(StripePaymentType::class);

        $parameters =  [
            'order' => $order,
            'deliveryAddress' => $order->getShippingAddress(),
            'restaurant' => $order->getRestaurant(),
            'form' => $form->createView(),
        ];

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $stripeToken = $form->get('stripeToken')->getData();

            $stripePayment->setStripeToken($stripeToken);

            // Create order, to generate a number
            $order->setCustomer($this->getUser());

            $orderStateMachine = $stateMachineFactory->get($order, OrderTransitions::GRAPH);
            $orderStateMachine->apply(OrderTransitions::TRANSITION_CREATE);

            $this->get('sylius.manager.order')->flush();

            if (PaymentInterface::STATE_FAILED === $stripePayment->getState()) {
                return array_merge($parameters, [
                    'error' => $stripePayment->getLastError()
                ]);
            }

            $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
            $request->getSession()->remove($sessionKeyName);

            return $this->redirectToRoute('profile_order', array('id' => $order->getId()));
        }

        return $parameters;
    }
}
