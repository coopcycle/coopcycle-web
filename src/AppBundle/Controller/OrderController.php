<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Form\OrderType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/order")
 */
class OrderController extends Controller
{
    use DoctrineTrait;

    private function getCart(Request $request)
    {
        return $request->getSession()->get('cart');
    }

    private function createOrderFromRequest(Request $request)
    {
        $cart = $this->getCart($request);

        $menuItemRepository = $this->getDoctrine()->getRepository(MenuItem::class);
        $restaurantRepository = $this->getDoctrine()->getRepository(Restaurant::class);

        $restaurant = $restaurantRepository->find($cart->getRestaurantId());

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->setCustomer($this->getUser());

        foreach ($cart->getItems() as $item) {
            $menuItem = $menuItemRepository->find($item['id']);
            $order->addMenuItem($menuItem, $item['quantity']);
        }

        $delivery = new Delivery($order);
        $delivery->setDate($cart->getDate());
        $delivery->setOriginAddress($restaurant->getAddress());

        return $order;
    }

    /**
     * @Route("/", name="order")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        if (null === $this->getCart($request)) {
            return [];
        }

        $order = $this->createOrderFromRequest($request);

        $form = $this->createForm(OrderType::class, $order);

        if (!$request->isMethod('POST') && $request->getSession()->has('deliveryAddress')) {
            $deliveryAddress = $request->getSession()->get('deliveryAddress');
            $deliveryAddress = $this->getDoctrine()
                ->getManagerForClass(Address::class)->merge($deliveryAddress);

            $form->get('deliveryAddress')->setData($deliveryAddress);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order = $form->getData();
            $deliveryAddress = $form->get('deliveryAddress')->getData();

            $createDeliveryAddress = $form->get('createDeliveryAddress')->getData();

            $this->getDoctrine()->getManagerForClass('AppBundle:DeliveryAddress')->persist($deliveryAddress);
            $this->getDoctrine()->getManagerForClass('AppBundle:DeliveryAddress')->flush();

            if ($createDeliveryAddress) {
                $this->getUser()->addAddress($deliveryAddress);
                $this->getDoctrine()->getManagerForClass('AppBundle:ApiUser')->flush();
            }

            $request->getSession()->set('deliveryAddress', $deliveryAddress);

            return $this->redirectToRoute('order_payment');
        }

        return array(
            'form' => $form->createView(),
            'google_api_key' => $this->getParameter('google_api_key'),
            'restaurant' => $order->getRestaurant(),
            'has_delivery_address' => count($this->getUser()->getAddresses()) > 0,
            'cart' => $this->getCart($request),
        );
    }

    /**
     * @Route("/payment", name="order_payment")
     * @Template()
     */
    public function paymentAction(Request $request)
    {
        if (!$request->getSession()->has('deliveryAddress')) {
            return $this->redirectToRoute('order');
        }

        $order = $this->createOrderFromRequest($request);
        $paymentService = $this->get('payment_service');

        $deliveryAddress = $request->getSession()->get('deliveryAddress');
        $deliveryAddress = $this->getDoctrine()
            ->getManagerForClass('AppBundle:Address')->merge($deliveryAddress);

        $order->getDelivery()->setDeliveryAddress($deliveryAddress);

        if ($request->isMethod('POST') && $request->request->has('stripeToken')) {
            $this->getDoctrine()->getManagerForClass('AppBundle:Order')->persist($order);
            $this->getDoctrine()->getManagerForClass('AppBundle:Order')->flush();

            try {
                $token = $request->request->get('stripeToken');
                $paymentService->createCharge($order, $token);
            } catch (\Exception $e) {
                return $this->redirectToRoute('order_error', array('id' => $order->getId()));
            }

            $order->setStatus(Order::STATUS_WAITING);

            $this->getDoctrine()
                ->getManagerForClass('AppBundle:Order')->flush();

            $this->get('event_dispatcher')
                ->dispatch('order.payment_success', new GenericEvent($order));

            $request->getSession()->remove('cart');
            $request->getSession()->remove('deliveryAddress');

            return $this->redirectToRoute('profile_order', array('id' => $order->getId()));
        }

        return array(
            'order' => $order,
            'restaurant' => $order->getRestaurant(),
            'stripe_publishable_key' => $this->getParameter('stripe_publishable_key')
        );
    }
}
