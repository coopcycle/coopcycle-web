<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
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

        $finder = new Finder();
        $finder->files()
            ->in($this->getParameter('kernel.root_dir') . '/../web/img/cuisine')
            ->name('*.jpg');

        $images = [];
        foreach ($finder as $file) {
            $images[] = $file->getBasename('.jpg');
        }

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
            'images' => $images,
        );
    }

    /**
     * @Route("/restaurant/{id}-{slug}", name="restaurant",
     *   requirements={"id" = "\d+", "slug" = "([a-z0-9-]+)"},
     *   defaults={"slug" = ""}
     * )
     * @Template()
     */
    public function indexAction($id, $slug, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository('AppBundle:Restaurant')->find($id);

        $translator = $this->get('translator');

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        $now = new \DateTime();
        $nextOpeningDate = $restaurant->getNextOpeningDate();

        if ($now->format('Y-m-d') === $nextOpeningDate->format('Y-m-d')) {
            $dates[] = $translator->trans('Today');
        }
        $dates[] = $translator->trans('Tomorrow');

        $times = [];
        $date = clone $nextOpeningDate;

        while ($restaurant->isOpen($date)) {
            $date->modify('+15 minutes');
            $times[] = $date->format('H:i');
        }

        return array(
            'restaurant' => $restaurant,
            'dates' => $dates,
            'times' => $times,
            'appetizers' => $restaurant->getProductsByCategory('Entrées'),
            'main_courses' => $restaurant->getProductsByCategory('Plats'),
            'desserts' => $restaurant->getProductsByCategory('Desserts'),
            'cart' => $this->getCart($request, $restaurant),
        );
    }

    /**
     * @Route("/restaurant/{id}/cart", name="restaurant_add_to_cart")
     */
    public function addToCartAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository('AppBundle:Restaurant')->find($id);

        $cart = $this->getCart($request, $restaurant);

        if (!$request->request->has('product')) {

            return new JsonResponse($cart->toArray());
        }

        // TODO Check if product belongs to restaurant
        $productId = $request->request->get('product');
        $product = $this->getDoctrine()
            ->getRepository('AppBundle:Product')->find($productId);

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
