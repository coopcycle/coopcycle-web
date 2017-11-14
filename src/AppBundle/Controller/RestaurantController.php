<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Utils\Cart;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\GeoUtils;
use AppBundle\Utils\RestaurantMismatchException;
use AppBundle\Utils\UnavailableProductException;
use Doctrine\Common\Collections\Criteria;
use League\Geotools\Coordinate\Coordinate;
use Psr\Log\LoggerInterface;
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

    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

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

    private function setCartAddress(Request $request, Cart $cart) {
        // check if the user has already the address registered
        $user = $this->getUser();
        $addressData = $request->request->get('address');

        if ($user) {

            // TODO : this doesn't work, maybe a bug in doctrine-postgis
            // TODO: investigate it
//            $criteria = Criteria::create()->where(
//                Criteria::expr()->eq(
//                    "geo",
//                    GeoUtils::asPoint(new GeoCoordinates($addressData['latitude'], $addressData['longitude']))
//                )
//            );
//
//            $address = $user->getAddresses()->matching($criteria);
            // the field `geo` on address is not hydrated correctly and is set to the DB value as string, instead of POINT(xxx,yyy)

            $geotools = new Geotools();

            // we need to compute the geohash, because of small differences between geodata from the back and from the front
            $address = $user->getAddresses()->filter(
                function ($address) use ($addressData, $geotools) {
                    $addressCoordinate = new Coordinate([$address->getGeo()->getLatitude(), $address->getGeo()->getLongitude()]);
                    $basketCoordinate = new Coordinate([$addressData['latitude'], $addressData['longitude']]);
                    return $geotools->geohash()->encode($addressCoordinate, 9)->getGeohash() ===
                        $geotools->geohash()->encode($basketCoordinate, 9)->getGeohash();
                }
            );

            if ($address->count() > 0) {
                $this->logger->debug('Existing address found for streetAddress '.$addressData['streetAddress']);
                $cart->setAddress($address->first());

                return $cart;
            }
        }

        $this->logger->debug('New address for user');

        $address = new Address();
        $address->setAddressLocality($addressData['addressLocality']);
        $address->setAddressCountry($addressData['addressCountry']);
        $address->setAddressRegion($addressData['addressRegion']);
        $address->setPostalCode($addressData['postalCode']);
        $address->setStreetAddress($addressData['streetAddress']);
        $address->setGeo(new GeoCoordinates($addressData['latitude'], $addressData['longitude']));

        $cart->setAddress($address);
        return $address;
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

        if ($request->query->has('geohash') && strlen($request->query->get('geohash')) > 0) {
            $geotools = new Geotools();
            $geohash = $request->query->get('geohash');

            $decoded = $geotools->geohash()->decode($geohash);

            $latitude = $decoded->getCoordinate()->getLatitude();
            $longitude = $decoded->getCoordinate()->getLongitude();

            // FIXME : can't use SQL because we want to filter by date as well :(
            // $count = $repository->countNearby($latitude, $longitude, 1500);
            // $matches = $repository->findNearby($latitude, $longitude, 1500, , self::ITEMS_PER_PAGE, $offset);

            $matches = $repository->findNearby($latitude, $longitude, 1500);
        } else {

            // FIXME : can't use SQL because we want to filter by date as well :(
            // $count = $repository->createQueryBuilder('r')->select('COUNT(r)')->getQuery()->getSingleScalarResult();

            $matches = $repository->findBy([], ['name' => 'ASC']);
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

        $matches = array_slice($matches, $offset, self::ITEMS_PER_PAGE);

        $pages = ceil($count / self::ITEMS_PER_PAGE);

        // pass user addresses to fill AddressPicker
        $user = $this->getUser();

        if ($user) {
            $addresses = $user->getAddresses();
        }
        else {
            $addresses = [];
        }

        return array(
            'count' => $count,
            'google_api_key' => $this->getParameter('google_api_key'),
            'searchDate' => $date->format(\DateTime::ATOM),
            'restaurants' => $matches,
            'page' => $page,
            'pages' => $pages,
            'geohash' => $request->query->get('geohash'),
            'images' => $images,
            'addresses' => $addresses
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
            'google_api_key' => $this->getParameter('google_api_key'),
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
            $repo = $this->getDoctrine()->getRepository(MenuItem::class);
            $menuItem = $repo->find($item['menuItemId']);
            $modifierChoices = isset($item['modifiers']) ? $item['modifiers'] : [];
            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;

            try {
                $cart->addItem($menuItem, $quantity, $modifierChoices);
            } catch (RestaurantMismatchException $e) {
                return new JsonResponse(['error' => 'Unable to add item'], 400);
            } catch (UnavailableProductException $e) {
                return new JsonResponse(['error' => 'Unable to add item'], 400);
            }
        }

        if ($request->request->has('date')) {
            $cart->setDate(new \DateTime($request->request->get('date')));
        }

        if ($request->request->has('address')) {
            $this->setCartAddress($request, $cart);
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
