<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\ValidationUtils;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class RestaurantController extends Controller
{
    const ITEMS_PER_PAGE = 15;

    private function setCartAddress(OrderInterface $cart, Request $request) {

        // TODO Avoid duplicating adresses
        // If the user is authenticated,
        // try to match the address with an existing address

        $addressData = $request->request->get('address');

        $address = $cart->getShippingAddress();
        if (null === $address) {
            $address = new Address();
        }

        $address->setAddressLocality($addressData['addressLocality']);
        $address->setAddressCountry($addressData['addressCountry']);
        $address->setAddressRegion($addressData['addressRegion']);
        $address->setPostalCode($addressData['postalCode']);
        $address->setStreetAddress($addressData['streetAddress']);
        $address->setGeo(new GeoCoordinates($addressData['latitude'], $addressData['longitude']));

        $cart->setShippingAddress($address);

        return $address;
    }

    private function addMenuItemModifiersAdjustment(OrderItemInterface $cartItem, MenuItem $menuItem, array $payload)
    {
        $adjustmentFactory = $this->get('sylius.factory.adjustment');

        foreach ($payload as $menuItemModifierId => $modifiersIds) {

            $menuItemModifier = $menuItem->getModifiers()
                ->filter(function ($menuItemModifier) use ($menuItemModifierId) {
                    return $menuItemModifier->getId() == $menuItemModifierId;
                })
                ->first();

            foreach ($modifiersIds as $modifierId) {

                $modifier = $menuItemModifier->getModifierChoices()
                    ->filter(function ($modifier) use ($modifierId) {
                        return $modifier->getId() == $modifierId;
                    })
                    ->first();

                $adjustment = $adjustmentFactory->createWithData(
                    AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT,
                    $modifier->getName(),
                    (int) ($menuItemModifier->getModifierPrice($modifier) * 100),
                    $neutral = false
                );
                $cartItem->addAdjustment($adjustment);
            }
        }
    }

    private function jsonResponse(OrderInterface $cart, array $errors)
    {
        $serializerContext = [
            'groups' => ['order']
        ];

        return new JsonResponse([
            'cart'   => $this->get('serializer')->normalize($cart, 'json', $serializerContext),
            'errors' => $errors,
        ], count($errors) > 0 ? 400 : 200);
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

            $matches = $repository->findNearby($latitude, $longitude, 2200);
        } else {

            // FIXME : can't use SQL because we want to filter by date as well :(
            // $count = $repository->createQueryBuilder('r')->select('COUNT(r)')->getQuery()->getSingleScalarResult();

            $matches = $repository->findBy(['enabled' => true], ['name' => 'ASC']);
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
     *   requirements={"id" = "(\d+|__RESTAURANT_ID__)", "slug" = "([a-z0-9-]+)"},
     *   defaults={"slug" = ""}
     * )
     * @Template()
     */
    public function indexAction($id, $slug, Request $request)
    {
        $user = $this->getUser();

        // Preview mode for admin + restaurant owner
        if (isset($user) && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
            $restaurant = $this->getDoctrine()
                ->getRepository('AppBundle:Restaurant')->findOneBy(['id' => $id]);
        } else {
            $restaurant = $this->getDoctrine()
                ->getRepository('AppBundle:Restaurant')->findOneBy(['id' => $id, 'enabled' => true]);
        }

        if (!$restaurant ||
            (isset($user) && !$restaurant->isEnabled() && $user->hasRole('ROLE_RESTAURANT') && !$user->ownsRestaurant($restaurant))) {
            throw new NotFoundHttpException();
        }

        if ($slug) {
            $expectedSlug = $this->get('slugify')->slugify($restaurant->getName());
            if ($slug !== $expectedSlug) {
                return $this->redirectToRoute('restaurant', ['id' => $id, 'slug' => $expectedSlug]);
            }
        }

        // This will be used by RestaurantCartContext
        $request->getSession()->set('restaurantId', $id);

        return array(
            'restaurant' => $restaurant,
            'availabilities' => $restaurant->getAvailabilities(),
        );
    }

    /**
     * @Route("/restaurant/{id}/cart", name="restaurant_cart", methods={"GET", "POST"})
     */
    public function cartAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        // This will be used by RestaurantCartContext
        $request->getSession()->set('restaurantId', $id);

        $cart = $this->get('sylius.context.cart')->getCart();

        if ($cart->getRestaurant() !== $restaurant) {
            $errors = [
                'restaurant' => [
                    sprintf('Restaurant mismatch')
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        if ($request->isMethod('POST')) {

            if ($request->request->has('date')) {
                $cart->setShippedAt(new \DateTime($request->request->get('date')));
            }

            if ($request->request->has('address')) {
                $this->setCartAddress($cart, $request);
            }

            $this->get('sylius.manager.order')->persist($cart);
            $this->get('sylius.manager.order')->flush();

            // TODO Find a better way to do this
            $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
            $request->getSession()->set($sessionKeyName, $cart->getId());
        }

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/reset", name="restaurant_cart_reset", methods={"POST"})
     */
    public function resetCartAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        // This will be used by RestaurantCartContext
        $request->getSession()->set('restaurantId', $id);

        $cart = $this->get('sylius.context.cart')->getCart();

        $cart->clearItems();
        $cart->setRestaurant($restaurant);

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{restaurantId}/cart/menu-item/{menuItemId}", name="restaurant_add_menu_item_to_cart", methods={"POST"})
     */
    public function addMenuItemToCartAction($restaurantId, $menuItemId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($restaurantId);

        $cart = $this->get('sylius.context.cart')->getCart();

        $menuItem = $this->getDoctrine()
            ->getRepository(MenuItem::class)->find($menuItemId);

        if (!$menuItem->isAvailable()) {
            $errors = [
                'items' => [
                    sprintf('Item %s is not available', $menuItem->getName())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        if ($menuItem->getRestaurant() !== $cart->getRestaurant()) {
            $errors = [
                'restaurant' => [
                    sprintf('Unable to add %s', $menuItem->getName())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        $quantity = $request->request->getInt('quantity', 1);

        $productVariantRepository = $this->get('sylius.repository.product_variant');
        $orderItemFactory = $this->get('sylius.factory.order_item');

        $productVariant = $productVariantRepository->findOneByMenuItem($menuItem);

        $cartItem = $orderItemFactory->createNew();
        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        if ($request->request->has('modifiers')) {
            $this->addMenuItemModifiersAdjustment($cartItem, $menuItem, $request->request->get('modifiers'));
        }

        $this->get('sylius.order_item_quantity_modifier')->modify($cartItem, $quantity);

        $this->get('sylius.order_modifier')->addToOrder($cart, $cartItem);

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        // TODO Find a better way to do this
        $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
        $request->getSession()->set($sessionKeyName, $cart->getId());

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{restaurantId}/cart/{cartItemId}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($restaurantId, $cartItemId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($restaurantId);

        $cart = $this->get('sylius.context.cart')->getCart();
        $cartItem = $this->get('sylius.repository.order_item')->find($cartItemId);

        $this->get('sylius.order_modifier')->removeFromOrder($cart, $cartItem);

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
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
        }, $this->getDoctrine()->getRepository(Restaurant::class)->findBy(['enabled' => true]));

        return [
            'restaurants' => $this->get('serializer')->serialize($restaurants, 'json'),
        ];
    }
}
