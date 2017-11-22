<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Form\DeliveryAddressType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/order")
 */
class OrderController extends Controller
{
    private function getCart(Request $request)
    {
        return $request->getSession()->get('cart');
    }

    private function createOrderFromRequest(Request $request)
    {
        $cart = $this->getCart($request);

        $restaurantRepository = $this->getDoctrine()->getRepository(Restaurant::class);

        $restaurant = $restaurantRepository->find($cart->getRestaurantId());

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->setCustomer($this->getUser());

        foreach ($cart->getItems() as $item) {
            $menuItemRepo = $this->getDoctrine()->getRepository(MenuItem::class);
            $menuItem = $menuItemRepo->find($item->getMenuItem()->getId());
            $order->addCartItem($item, $menuItem);
        }

        $delivery = new Delivery($order);
        $delivery->setDate($cart->getDate());
        $delivery->setOriginAddress($restaurant->getAddress());
        $delivery->setDeliveryAddress($cart->getAddress());
        $delivery->setPrice($restaurant->getContract()->getFlatDeliveryPrice());

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
        $deliveryAddress =  $order->getDelivery()->getDeliveryAddress();

        $form = $this->createForm(DeliveryAddressType::class, $deliveryAddress);

        if (!$request->isMethod('POST') && $request->getSession()->has('deliveryAddress')) {
            $deliveryAddress = $request->getSession()->get('deliveryAddress');
            $deliveryAddress = $this->getDoctrine()
                ->getManagerForClass(Address::class)->merge($deliveryAddress);

            $form->setData($deliveryAddress);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $deliveryAddress = $form->getData();

            if (!is_null($deliveryAddress->getId())) {
                $this->getDoctrine()->getManagerForClass('AppBundle:Address')->persist($deliveryAddress);
                $this->getDoctrine()->getManagerForClass('AppBundle:Address')->flush();

                $this->getUser()->addAddress($deliveryAddress);

                $this->getDoctrine()->getManagerForClass('AppBundle:ApiUser')->flush();
            }

            $request->getSession()->set('deliveryAddress', $deliveryAddress);

            return $this->redirectToRoute('order_payment');
        }

        return array(
            'order' => $order,
            'form' => $form->createView(),
            'google_api_key' => $this->getParameter('google_api_key'),
            'restaurant' => $order->getRestaurant(),
            'deliveryAddress' => $deliveryAddress,
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
        $orderManager = $this->get('order.manager');

        $deliveryAddress = $request->getSession()->get('deliveryAddress');
        $deliveryAddress = $this->getDoctrine()
            ->getManagerForClass('AppBundle:Address')->merge($deliveryAddress);

        $order->getDelivery()->setDeliveryAddress($deliveryAddress);

        $templateData =  [
            'order' => $order,
            'deliveryAddress' => $order->getDelivery()->getDeliveryAddress(),
            'restaurant' => $order->getRestaurant(),
            'stripe_publishable_key' => $this->getParameter('stripe_publishable_key')
        ];

        if ($request->isMethod('POST') && $request->request->has('stripeToken')) {

            $this->getDoctrine()->getManagerForClass(Order::class)->persist($order);
            $this->getDoctrine()->getManagerForClass(Order::class)->flush();

            try {

                $orderManager->pay($order, $request->request->get('stripeToken'));
            } catch (\Exception $e) {
                $templateData['error'] = $e->getMessage();
                $order->setStatus(Order::STATUS_PAYMENT_ERROR);

                return $templateData;
            } finally {
                $this->getDoctrine()->getManagerForClass(Order::class)->flush();
            }

            $request->getSession()->remove('cart');
            $request->getSession()->remove('deliveryAddress');

            return $this->redirectToRoute('profile_order', array('id' => $order->getId()));
        }

        return $templateData;
    }

    /**
     * @Route("/public/{uuid}", name="order_public")
     * @Template("@App/Order/public.html.twig")
     * @param Request $request
     *
     */
    public function orderPublic($uuid, Request $request) {

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
