<?php

namespace AppBundle\Controller;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Form\Order\CartType;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\ValidationUtils;
use Carbon\Carbon;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Product\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class RestaurantController extends Controller
{
    const ITEMS_PER_PAGE = 15;

    private function matchNonExistingOption(ProductInterface $product, array $optionValues)
    {
        foreach ($optionValues as $optionValue) {
            if (!$product->hasOption($optionValue->getOption())) {
                return $optionValue->getOption();
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
            'availabilities' => $this->getAvailabilities($cart),
            'errors' => $errors,
        ], count($errors) > 0 ? 400 : 200);
    }

    private function customizeSeoPage(Restaurant $restaurant, Request $request)
    {
        $seoPage = $this->get('sonata.seo.page');
        $seoPage->setTitle(sprintf('%s - CoopCycle', $restaurant->getName()));
        $seoPage
            ->addMeta('property', 'og:title', $seoPage->getTitle())
            ->addMeta('property', 'og:description', sprintf('%s, %s %s',
                $restaurant->getAddress()->getStreetAddress(),
                $restaurant->getAddress()->getPostalCode(),
                $restaurant->getAddress()->getAddressLocality()
            ))
            // https://developers.facebook.com/docs/reference/opengraph/object-type/restaurant.restaurant/
            ->addMeta('property', 'og:type', 'restaurant.restaurant')
            ->addMeta('property', 'restaurant:contact_info:street_address', $restaurant->getAddress()->getStreetAddress())
            ->addMeta('property', 'restaurant:contact_info:locality', $restaurant->getAddress()->getAddressLocality())
            ->addMeta('property', 'restaurant:contact_info:website', $restaurant->getWebsite())
            ->addMeta('property', 'place:location:latitude', $restaurant->getAddress()->getGeo()->getLatitude())
            ->addMeta('property', 'place:location:longitude', $restaurant->getAddress()->getGeo()->getLongitude())
            ;

        $uploaderHelper = $this->get('vich_uploader.templating.helper.uploader_helper');
        $imagePath = $uploaderHelper->asset($restaurant, 'imageFile');
        if (null !== $imagePath) {
            $seoPage->addMeta('property', 'og:image', $request->getUriForPath($imagePath));
        }
    }

    private function getAvailabilities(OrderInterface $cart)
    {
        $restaurant = $cart->getRestaurant();

        $availabilities = $restaurant->getAvailabilities();

        $availabilities = array_filter($availabilities, function ($date) use ($cart) {
            $shippingDate = new \DateTime($date);

            return $this->get('coopcycle.shipping_date_filter')->accept($cart, $shippingDate);
        });

        // Make sure to return a zero-indexed array
        return array_values($availabilities);
    }

    /**
     * @Route("/restaurants", name="restaurants")
     * @Template()
     */
    public function listAction(Request $request)
    {
        $repository = $this->get('coopcycle.repository.restaurant');

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

            $matches = $repository->findByLatLng($latitude, $longitude);
        } else {

            $matches = $repository->findAll([]);

            // 1 - opened restaurants
            // 2 - closed restaurants
            // 3 - disabled restaurants
            usort($matches, function (Restaurant $a, Restaurant $b) {

                $isAEnabled = $a->isEnabled();
                $isBEnabled = $b->isEnabled();

                $compareIsEnabled = intval($isBEnabled) - intval($isAEnabled);

                if ($compareIsEnabled !== 0) {
                    return $compareIsEnabled;
                }

                $isAOpen = $a->isOpen();
                $isBOpen = $b->isOpen();

                $compareIsOpen = intval($isBOpen) - intval($isAOpen);

                if ($compareIsOpen !== 0) {
                    return $compareIsOpen;
                }

                $aNextOpening = $a->getNextOpeningDate();
                $bNextOpening = $b->getNextOpeningDate();

                $compareNextOpening = $aNextOpening === $bNextOpening ? 0 : ($aNextOpening < $bNextOpening ? -1 : 1);

                return $compareNextOpening;
            });
        }

        $count = count($matches);

        $matches = array_slice($matches, $offset, self::ITEMS_PER_PAGE);

        $pages = ceil($count / self::ITEMS_PER_PAGE);

        return array(
            'count' => $count,
            'restaurants' => $matches,
            'page' => $page,
            'pages' => $pages,
            'geohash' => $request->query->get('geohash'),
            'images' => $images,
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
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        if (!$restaurant) {
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

        $cart = $this->get('sylius.context.cart')->getCart();

        $cartForm = $this->createForm(CartType::class, $cart, [
            'validation_groups' => 'Checkout'
        ]);

        if ($request->isMethod('POST')) {

            $cartForm->handleRequest($request);

            $cart = $cartForm->getData();

            if ($request->isXmlHttpRequest()) {

                // Make sure the shipping address is valid
                // FIXME This is cumbersome, there should be a better way
                $shippingAddress = $cart->getShippingAddress();
                if (null !== $shippingAddress) {
                    $isShippingAddressValid = count($this->get('validator')->validate($shippingAddress)) === 0;
                    if (!$isShippingAddressValid) {
                        $cart->setShippingAddress(null);
                    }
                }

                if ($cart->getRestaurant() !== $restaurant) {
                    // Alert customer only when there is something in the cart
                    // Customer may be browsing the available restaurants,
                    // so no need to alert all the time
                    if ($cart->getItemsTotal() > 0) {
                        $errors = [
                            'restaurant' => [
                                sprintf('Restaurant mismatch')
                            ]
                        ];

                        return $this->jsonResponse($cart, $errors);
                    } else {
                        $cart->setRestaurant($restaurant);
                    }
                }

                $errors = [];

                if (!$cartForm->isValid()) {
                    foreach ($cartForm->getErrors() as $formError) {
                        $propertyPath = (string) $formError->getOrigin()->getPropertyPath();
                        $errors[$propertyPath] = [$formError->getMessage()];
                    }
                }

                $this->get('sylius.manager.order')->persist($cart);
                $this->get('sylius.manager.order')->flush();

                // TODO Find a better way to do this
                $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
                $request->getSession()->set($sessionKeyName, $cart->getId());

                return $this->jsonResponse($cart, $errors);

            } else {

                // The cart is valid, and the user clicked on the submit button
                if ($cartForm->isValid()) {
                    $this->get('sylius.manager.order')->flush();

                    return $this->redirectToRoute('order');
                }
            }
        }

        $this->customizeSeoPage($restaurant, $request);

        $delay = null;
        if ($restaurant->getOrderingDelayMinutes() > 0) {
            Carbon::setLocale($request->attributes->get('_locale'));
            $delay = Carbon::now()
                ->addMinutes($restaurant->getOrderingDelayMinutes())
                ->diffForHumans(null, true);
        }

        return array(
            'restaurant' => $restaurant,
            'availabilities' => $this->getAvailabilities($cart),
            'delay' => $delay,
            'cart_form' => $cartForm->createView(),
        );
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
        $cart->setShippedAt($restaurant->getNextOpeningDate());

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/product/{code}", name="restaurant_add_product_to_cart", methods={"POST"})
     */
    public function addProductToCartAction($id, $code, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $product = $this->get('sylius.repository.product')
            ->findOneByCode($code);

        $cart = $this->get('sylius.context.cart')->getCart();

        if (!$product->isEnabled()) {
            $errors = [
                'items' => [
                    sprintf('Product %s is not enabled', $product->getCode())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        if (!$restaurant->hasProduct($product)) {
            $errors = [
                'restaurant' => [
                    sprintf('Unable to add product %s', $product->getCode())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        $quantity = $request->request->getInt('quantity', 1);

        $cartItem = $this->get('sylius.factory.order_item')->createNew();

        $variantResolver = $this->get('coopcycle.sylius.product_variant_resolver.lazy');

        if (!$product->hasOptions()) {
            $productVariant = $variantResolver->getVariant($product);
        } else {

            if (!$request->request->has('options') && !$product->hasNonAdditionalOptions()) {
                $productVariant = $variantResolver->getVariant($product);
            } else {
                $productOptionValueRepository = $this->get('sylius.repository.product_option_value');
                $options = $request->request->get('options');

                $optionValues = [];
                foreach ($options as $optionCode => $optionValueCode) {

                    $optionValueCodes = [];
                    if (is_array($optionValueCode)) {
                        $optionValueCodes = $optionValueCode;
                    } else {
                        $optionValueCodes[] = $optionValueCode;
                    }

                    foreach ($optionValueCodes as $optionValueCode) {
                        $optionValue = $productOptionValueRepository->findOneByCode($optionValueCode);
                        $optionValues[] = $optionValue;
                    }
                }

                $nonExistingOption = $this->matchNonExistingOption($product, $optionValues);
                if (null !== $nonExistingOption) {
                    $errors = [
                        'items' => [
                            sprintf('Product %s does not have option %s', $product->getCode(), $nonExistingOption->getCode())
                        ]
                    ];

                    return $this->jsonResponse($cart, $errors);
                }

                $productVariant = $variantResolver->getVariantForOptionValues($product, $optionValues);
            }
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

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
     * @Route("/restaurant/{id}/cart/{cartItemId}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($id, $cartItemId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $cart = $this->get('sylius.context.cart')->getCart();
        $cartItem = $this->get('sylius.repository.order_item')->find($cartItemId);

        if ($cartItem) {
            $this->get('sylius.order_modifier')->removeFromOrder($cart, $cartItem);

            $this->get('sylius.manager.order')->persist($cart);
            $this->get('sylius.manager.order')->flush();
        }

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
        }, $this->getDoctrine()->getRepository(Restaurant::class)->findAll());

        return [
            'restaurants' => $this->get('serializer')->serialize($restaurants, 'json'),
        ];
    }
}
