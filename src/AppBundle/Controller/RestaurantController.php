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
    use DoctrineTrait;

    const ITEMS_PER_PAGE = 15;

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

        $page = $request->query->getInt('page', 1);

        if ($request->query->has('geohash')) {

            $geotools = new Geotools();
            $geohash = $request->query->get('geohash');

            $decoded = $geotools->geohash()->decode($geohash);

            $latitude = $decoded->getCoordinate()->getLatitude();
            $longitude = $decoded->getCoordinate()->getLongitude();

            $count = $repository->countNearby($latitude, $longitude, 1500);
            $pages = ceil($count / self::ITEMS_PER_PAGE);

            $offset = ($page - 1) * self::ITEMS_PER_PAGE;
            $matches = $repository->findNearby($latitude, $longitude, 1500, self::ITEMS_PER_PAGE, $offset);

        } else {
            $pages = 1;
            $matches = $repository->findBy([], ['name' => 'ASC'], self::ITEMS_PER_PAGE);
        }

        return array(
            'restaurants' => $matches,
            'page' => $page,
            'pages' => $pages,
            'geohash' => $request->query->get('geohash'),
        );
    }

    /**
     * @Route("/restaurant/{id}-{slug}", name="restaurant")
     * @Template()
     */
    public function indexAction($id, $slug, Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $restaurant = $repository->find($id);

        return array(
            'restaurant' => $restaurant,
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
     * @Route("/restaurant/{id}/cart/{product}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($id, $product, Request $request)
    {
        $restaurantRepository = $this->getRepository('Restaurant');
        $productRepository = $this->getRepository('Product');

        $restaurant = $restaurantRepository->find($id);
        $product = $productRepository->find($product);

        $cart = $this->getCart($request, $restaurant);
        $cart->removeProduct($product);
        $this->saveCart($request, $cart);

        return new JsonResponse($cart->toArray());
    }
}
