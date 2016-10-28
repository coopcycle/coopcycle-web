<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;

class RestaurantController extends Controller
{
    private function getCart(Request $request, Restaurant $restaurant)
    {
        if (!$cart = $request->getSession()->get('cart')) {
            $cart = new Cart($restaurant);
        }

        if (!$cart->isForRestaurant($restaurant)) {
            $cart = new Cart($restaurant);
        }

        return $cart;
    }

    private function saveCart(Request $request, Cart $cart)
    {
        $request->getSession()->set('cart', $cart);
    }

    /**
     * @Route("/restaurants", name="restaurants")
     * @Template()
     */
    public function listAction(Request $request)
    {
        $manager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\Restaurant');
        $repository = $manager->getRepository('AppBundle\\Entity\\Restaurant');

        if ($request->query->has('geohash')) {

            $geotools = new Geotools();
            $geohash = $request->query->get('geohash');

            $decoded = $geotools->geohash()->decode($geohash);

            $latitude = $decoded->getCoordinate()->getLatitude();
            $longitude = $decoded->getCoordinate()->getLongitude();

            $matches = $repository->findNearby($latitude, $longitude);
        } else {
            $matches = $repository->findAll();
        }

        return array(
            'restaurants' => $matches,
        );
    }

    /**
     * @Route("/restaurant/{id}", name="restaurant")
     * @Template()
     */
    public function indexAction($id, Request $request)
    {
        $manager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\Restaurant');
        $repository = $manager->getRepository('AppBundle\\Entity\\Restaurant');

        $restaurant = $repository->find($id);

        return array(
            'restaurant' => $restaurant,
            'address' => $request->getSession()->get('address'),
            'cart' => $this->getCart($request, $restaurant),
        );
    }

    /**
     * @Route("/restaurant/{id}/cart", name="restaurant_add_to_cart")
     */
    public function addToCartAction($id, Request $request)
    {
        $restaurantManager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\Restaurant');
        $restaurantRepository = $restaurantManager->getRepository('AppBundle\\Entity\\Restaurant');

        $productManager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\Product');
        $productRepository = $productManager->getRepository('AppBundle\\Entity\\Product');

        $restaurant = $restaurantRepository->find($id);

        // TODO Check if product belongs to restaurant

        $productId = $request->request->get('product');
        $product = $productRepository->find($productId);

        $cart = $this->getCart($request, $restaurant);
        $cart->addProduct($product);
        $this->saveCart($request, $cart);

        return new JsonResponse($cart->toArray());
    }

    /**
     * @Route("/restaurant/{id}/order")
     */
    public function orderAction()
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
    }
}
