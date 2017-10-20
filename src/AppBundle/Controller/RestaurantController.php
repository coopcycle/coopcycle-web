<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use League\Geotools\Geotools;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class RestaurantController extends Controller
{
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
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        if ($request->query->has('geohash')) {
            $geotools = new Geotools();
            $geohash = $request->query->get('geohash');

            $decoded = $geotools->geohash()->decode($geohash);

            $latitude = $decoded->getCoordinate()->getLatitude();
            $longitude = $decoded->getCoordinate()->getLongitude();

//            $count = $repository->countNearby($latitude, $longitude, 1500);

            $matches = $repository->findNearby($latitude, $longitude, 1500, self::ITEMS_PER_PAGE, $offset);
        } else {
//            $count = $repository->createQueryBuilder('r')->select('COUNT(r)')->getQuery()->getSingleScalarResult();
            $matches = $repository->findBy([], ['name' => 'ASC'], self::ITEMS_PER_PAGE);
        }

        if ($request->query->has('datetime')) {
            $date = new \DateTime($request->query->get('datetime'));
        } else {
            $date = new \DateTime();
        }

        $matches = array_filter(
            $matches,
            function ($item) use ($date) {
                return $item->isOpen($date);
            }
        );

        $count = count($matches);
        $pages = ceil($count / self::ITEMS_PER_PAGE);

        return array(
            'google_api_key' => $this->getParameter('google_api_key'),
            'searchDate' => $date->format(\DateTime::ATOM),
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

        if ($slug) {
            $expectedSlug = $this->get('slugify')->slugify($restaurant->getName());
            if ($slug !== $expectedSlug) {
                return $this->redirectToRoute('restaurant', ['id' => $id, 'slug' => $expectedSlug]);
            }
        }

        $availabilities = $restaurant->getAvailabilities();

        return array(
            'restaurant' => $restaurant,
            'availabilities' => $availabilities,
            'cart' => $this->getCart($request, $restaurant),
        );
    }

    /**
     * @Route("/restaurant/{id}/cart", name="restaurant_add_to_cart")
     */
    public function addToCartAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $cart = $this->getCart($request, $restaurant);

        if ($request->request->has('selectedItemData')) {

            $item = $request->request->get('selectedItemData');

            // TODO Check if product belongs to restaurant
            $repo = $this->getDoctrine()->getRepository(MenuItem::class);

            $menuItem = $repo->find($item['menuItemId']);

            $modifierChoices = isset($item['modifiers']) ? $item['modifiers'] : [];

            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;

            $cart->addItem($menuItem, $quantity, $modifierChoices);
        }

        if ($request->request->has('date')) {
            $cart->setDate(new \DateTime($request->request->get('date')));
        }

        $this->saveCart($request, $cart);

        return new JsonResponse($cart->toArray());
    }

    /**
     * @Route("/restaurant/{id}/cart/{itemKey}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($id, $itemKey, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository('AppBundle:Restaurant')->find($id);

        $cart = $this->getCart($request, $restaurant);

        $cart->removeItem($itemKey);
        $this->saveCart($request, $cart);

        return new JsonResponse($cart->toArray());
    }

    /**
     * @Route("/restaurants/map", name="restaurants_map")
     * @Template()
     */
    public function mapAction(Request $request)
    {
        $restaurants = array_map(function (Restaurant $restaurant) {
            return [
                'name' => $restaurant->getName(),
                'address' => [
                    'geo' => [
                        'latitude'  => $restaurant->getAddress()->getGeo()->getLatitude(),
                        'longitude' => $restaurant->getAddress()->getGeo()->getLongitude(),
                    ]
                ],
                'url' => $this->generateUrl('restaurant', [
                    'id' => $restaurant->getId(),
                    'slug' => $this->get('slugify')->slugify($restaurant->getName())
                ])
            ];
        }, $this->getDoctrine()->getRepository(Restaurant::class)->findAll());

        return [
            'restaurants' => $this->get('serializer')->serialize($restaurants, 'json'),
        ];
    }
}
