<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\DeliveryAddress;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Entity\OrderItem;
use AppBundle\Entity\GeoCoordinates;
use AppBundle\Form\OrderType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Stripe;

class OrderController extends Controller
{
    use DoctrineTrait;

    private function getCart(Request $request)
    {
        return $request->getSession()->get('cart');
    }

    private function createOrder(Request $request)
    {
        $cart = $this->getCart($request);

        $productRepository = $this->getRepository('Product');
        $restaurantRepository = $this->getRepository('Restaurant');

        $restaurant = $restaurantRepository->find($cart->getRestaurantId());

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->setCustomer($this->getUser());

        foreach ($cart->getItems() as $item) {

            $product = $productRepository->find($item['id']);

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($item['quantity']);

            $order->addOrderedItem($orderItem);
        }

        return $order;
    }

    /**
     * @Route("/order", name="order")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $order = $this->createOrder($request);
        $form = $this->createForm(OrderType::class, $order);

        if (count($this->getUser()->getDeliveryAddresses()) > 0) {
            $form->get('createDeliveryAddress')->setData('0');
        } else {
            $form->get('createDeliveryAddress')->setData('1');
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $order = $form->getData();

            $deliveryAddress = $form->get('deliveryAddress')->getData();
            if (null === $deliveryAddress->getId()) {

                $latitude = $form->get('deliveryAddress')->get('latitude')->getData();
                $longitude = $form->get('deliveryAddress')->get('longitude')->getData();

                $deliveryAddress->setCustomer($this->getUser());
                $deliveryAddress->setGeo(new GeoCoordinates($latitude, $longitude));

                $this->getDoctrine()->getManagerForClass('AppBundle:DeliveryAddress')->persist($order->getDeliveryAddress());
                $this->getDoctrine()->getManagerForClass('AppBundle:DeliveryAddress')->flush();
            }

            $this->getDoctrine()->getManagerForClass('AppBundle:Order')->persist($order);
            $this->getDoctrine()->getManagerForClass('AppBundle:Order')->flush();

            return $this->redirectToRoute('order_payment', ['id' => $order->getId()]);
        }

        return array(
            'form' => $form->createView(),
            'google_api_key' => $this->getParameter('google_api_key'),
            'restaurant' => $order->getRestaurant(),
            'hasDeliveryAddress' => count($this->getUser()->getDeliveryAddresses()) > 0,
            'cart' => $this->getCart($request),
        );
    }

    /**
     * @Route("/order/{id}/delivery", name="order_delivery")
     * @Template("@App/Order/index.html.twig")
     */
    public function deliveryAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository('AppBundle:Order')->find($id);

        $form = $this->createForm(OrderType::class, $order);

        if (!$form->isSubmitted()) {
            if (null !== $order->getDeliveryAddress()) {
                $form->get('createDeliveryAddress')->setData('0');
            } else {
                $form->get('createDeliveryAddress')->setData('1');
            }
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $order = $form->getData();

            $this->getDoctrine()->getManagerForClass('AppBundle:Order')->flush();

            return $this->redirectToRoute('order_payment', ['id' => $order->getId()]);
        }

        return array(
            'order' => $order,
            'form' => $form->createView(),
            'restaurant' => $order->getRestaurant(),
            'hasDeliveryAddress' => count($this->getUser()->getDeliveryAddresses()) > 0,
            'cart' => $this->getCart($request),
        );
    }

    /**
     * @Route("/order/{id}/payment", name="order_payment")
     * @Template()
     */
    public function paymentAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository('AppBundle:Order')->find($id);

        if ($request->isMethod('POST')) {

            Stripe\Stripe::setApiKey($this->getParameter('stripe_secret_key'));

            $token = $request->request->get('stripeToken');

            try {
                $charge = Stripe\Charge::create(array(
                    "amount" => $order->getTotal() * 100, // Amount in cents
                    "currency" => "eur",
                    "source" => $token,
                    "description" => "Order #".$order->getId()
                ));
            } catch (Stripe\Error\Card $e) {
                return $this->redirectToRoute('order_error', array('id' => $order->getId()));
            }

            $order->setStatus(Order::STATUS_WAITING);

            $this->getDoctrine()
                ->getManagerForClass('AppBundle:Order')->flush();

            $redis = $this->get('snc_redis.default');

            $restaurant = $order->getRestaurant();
            $deliveryAddress = $order->getDeliveryAddress();

            $redis->geoadd(
                'orders:geo',
                $restaurant->getGeo()->getLongitude(),
                $restaurant->getGeo()->getLatitude(),
                'order:'.$order->getId()
            );

            $redis->geoadd(
                'restaurants:geo',
                $restaurant->getGeo()->getLongitude(),
                $restaurant->getGeo()->getLatitude(),
                'order:'.$order->getId()
            );
            $redis->geoadd(
                'delivery_addresses:geo',
                $deliveryAddress->getGeo()->getLongitude(),
                $deliveryAddress->getGeo()->getLatitude(),
                'order:'.$order->getId()
            );

            $redis->lpush(
                'orders:waiting',
                $order->getId()
            );

            $request->getSession()->remove('cart');

            return $this->redirectToRoute('order_confirm', array('id' => $order->getId()));
        }

        return array(
            'order' => $order,
            'restaurant' => $order->getRestaurant(),
            'stripe_publishable_key' => $this->getParameter('stripe_publishable_key')
        );
    }

    /**
     * @Route("/order/{id}/confirm", name="order_confirm")
     * @Template()
     */
    public function confirmAction($id, Request $request)
    {
        $order = $this->getRepository('Order')->find($id);

        return array(
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld'),
            'google_api_key' => $this->getParameter('google_api_key')
        );
    }
}
