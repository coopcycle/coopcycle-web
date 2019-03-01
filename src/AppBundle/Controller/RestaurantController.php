<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\Address;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\RestaurantRepository;
use AppBundle\Form\Order\CartType;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ValidationUtils;
use Carbon\Carbon;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Persistence\ObjectManager;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 * @HideSoftDeleted
 */
class RestaurantController extends AbstractController
{
    use UserTrait;

    const ITEMS_PER_PAGE = 15;

    private $orderManager;
    private $seoPage;
    private $uploaderHelper;

    public function __construct(
        ObjectManager $orderManager,
        SeoPageInterface $seoPage,
        UploaderHelper $uploaderHelper,
        ShippingDateFilter $shippingDateFilter,
        ValidatorInterface $validator,
        RepositoryInterface $productRepository,
        RepositoryInterface $orderItemRepository,
        $orderItemFactory,
        $productVariantResolver,
        RepositoryInterface $productOptionValueRepository,
        $orderItemQuantityModifier,
        $orderModifier)
    {
        $this->orderManager = $orderManager;
        $this->seoPage = $seoPage;
        $this->uploaderHelper = $uploaderHelper;
        $this->shippingDateFilter = $shippingDateFilter;
        $this->validator = $validator;
        $this->productRepository = $productRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->productVariantResolver = $productVariantResolver;
        $this->productOptionValueRepository = $productOptionValueRepository;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
    }

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
        $this->seoPage->addTitle($restaurant->getName());

        $description = $restaurant->getDescription();
        if (!empty($description)) {
            $this->seoPage->addMeta('name', 'description', $restaurant->getDescription());
        }

        $this->seoPage
            ->addMeta('property', 'og:title', $this->seoPage->getTitle())
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

        $imagePath = $this->uploaderHelper->asset($restaurant, 'imageFile');
        if (null !== $imagePath) {
            $this->seoPage->addMeta('property', 'og:image', $request->getUriForPath($imagePath));
        }
    }

    private function getAvailabilities(OrderInterface $cart)
    {
        $restaurant = $cart->getRestaurant();

        $availabilities = $restaurant->getAvailabilities();

        $availabilities = array_filter($availabilities, function ($date) use ($cart) {
            $shippingDate = new \DateTime($date);

            return $this->shippingDateFilter->accept($cart, $shippingDate);
        });

        // Make sure to return a zero-indexed array
        return array_values($availabilities);
    }

    /**
     * @Route("/restaurants", name="restaurants")
     * @Template()
     */
    public function listAction(Request $request, RestaurantRepository $repository)
    {
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
            'addresses_normalized' => $this->getUserAddresses(),
            'address' => $request->query->has('address') ? $request->query->get('address') : null,
        );
    }

    /**
     * @Route("/restaurant/{id}-{slug}", name="restaurant",
     *   requirements={"id" = "(\d+|__RESTAURANT_ID__)", "slug" = "([a-z0-9-]+)"},
     *   defaults={"slug" = ""}
     * )
     * @Template()
     */
    public function indexAction($id, $slug, Request $request, SlugifyInterface $slugify, CartContextInterface $cartContext)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        if ($slug) {
            $expectedSlug = $slugify->slugify($restaurant->getName());
            if ($slug !== $expectedSlug) {
                return $this->redirectToRoute('restaurant', ['id' => $id, 'slug' => $expectedSlug]);
            }
        }

        // This will be used by RestaurantCartContext
        $request->getSession()->set('restaurantId', $id);

        $cart = $cartContext->getCart();

        $user = $this->getUser();
        if ($request->query->has('address') && $user && count($user->getAddresses()) > 0) {

            $addressId = intval(base_convert($request->query->get('address'), 36, 10));

            $shippingAddress = $this->getDoctrine()
                ->getRepository(Address::class)
                ->find($addressId);

            if ($user->getAddresses()->contains($shippingAddress)) {
                $cart->setShippingAddress($shippingAddress);

                $this->orderManager->persist($cart);
                $this->orderManager->flush();

                // TODO Find a better way to do this
                $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
                $request->getSession()->set($sessionKeyName, $cart->getId());
            }
        }

        $cartForm = $this->createForm(CartType::class, $cart);

        if ($request->isMethod('POST')) {

            $cartForm->handleRequest($request);

            $cart = $cartForm->getData();

            if ($request->isXmlHttpRequest()) {

                $errors = [];

                // Customer may be browsing the available restaurants
                // Make sure the request targets the same restaurant
                // If not, we don't persist the cart
                if (null !== $cart->getId() && $cart->getRestaurant() !== $restaurant) {

                    return $this->jsonResponse($cart, $errors);
                }

                // Make sure the shipping address is valid
                // FIXME This is cumbersome, there should be a better way
                $shippingAddress = $cart->getShippingAddress();
                if (null !== $shippingAddress) {
                    $isShippingAddressValid = count($this->validator->validate($shippingAddress)) === 0;
                    if (!$isShippingAddressValid) {
                        $cart->setShippingAddress(null);
                    }
                }

                if (!$cartForm->isValid()) {
                    foreach ($cartForm->getErrors() as $formError) {
                        $propertyPath = (string) $formError->getOrigin()->getPropertyPath();
                        $errors[$propertyPath] = [$formError->getMessage()];
                    }
                }

                $this->orderManager->persist($cart);
                $this->orderManager->flush();

                // TODO Find a better way to do this
                $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
                $request->getSession()->set($sessionKeyName, $cart->getId());

                return $this->jsonResponse($cart, $errors);

            } else {

                // The cart is valid, and the user clicked on the submit button
                if ($cartForm->isValid()) {
                    $this->orderManager->flush();

                    return $this->redirectToRoute('order');
                }
            }
        }

        $this->customizeSeoPage($restaurant, $request);

        $structuredData = $this->get('serializer')->normalize($restaurant, 'jsonld', [
            'resource_class' => Restaurant::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['restaurant_seo', 'postal_address']
        ]);

        $delay = null;
        if ($restaurant->getOrderingDelayMinutes() > 0) {
            Carbon::setLocale($request->attributes->get('_locale'));
            $delay = Carbon::now()
                ->addMinutes($restaurant->getOrderingDelayMinutes())
                ->diffForHumans(null, true);
        }

        return array(
            'restaurant' => $restaurant,
            'structured_data' => $structuredData,
            'availabilities' => $this->getAvailabilities($cart),
            'delay' => $delay,
            'cart_form' => $cartForm->createView(),
            'addresses_normalized' => $this->getUserAddresses(),
        );
    }

    /**
     * @Route("/restaurant/{id}/cart/address", name="restaurant_cart_address", methods={"POST"})
     */
    public function changeAddressAction($id, Request $request, CartContextInterface $cartContext)
    {
        $cart = $cartContext->getCart();

        $user = $this->getUser();
        if ($request->request->has('address') && $user && count($user->getAddresses()) > 0) {

            $addressId = $request->request->get('address');

            $shippingAddress = $this->getDoctrine()
                ->getRepository(Address::class)
                ->find($addressId);

            if ($user->getAddresses()->contains($shippingAddress)) {
                $cart->setShippingAddress($shippingAddress);

                $this->orderManager->persist($cart);
                $this->orderManager->flush();
            }
        }

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/product/{code}", name="restaurant_add_product_to_cart", methods={"POST"})
     */
    public function addProductToCartAction($id, $code, Request $request, CartContextInterface $cartContext)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $product = $this->productRepository->findOneByCode($code);

        $cart = $cartContext->getCart();

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

        $clear = $request->request->getBoolean('_clear', false);

        if ($cart->getRestaurant() !== $product->getRestaurant() && !$clear) {
            $errors = [
                'restaurant' => [
                    sprintf('Restaurant mismatch')
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        if ($clear) {
            $cart->clearItems();
            $cart->setRestaurant($restaurant);
        }

        $quantity = $request->request->getInt('quantity', 1);

        $cartItem = $this->orderItemFactory->createNew();

        if (!$product->hasOptions()) {
            $productVariant = $this->productVariantResolver->getVariant($product);
        } else {

            if (!$request->request->has('options') && !$product->hasNonAdditionalOptions()) {
                $productVariant = $this->productVariantResolver->getVariant($product);
            } else {
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
                        $optionValue = $this->productOptionValueRepository->findOneByCode($optionValueCode);
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

                $productVariant = $this->productVariantResolver->getVariantForOptionValues($product, $optionValues);
            }
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        $this->orderItemQuantityModifier->modify($cartItem, $quantity);

        $this->orderModifier->addToOrder($cart, $cartItem);

        // FIXME
        // There is a possible race condition in the workflow
        // When a product is added to the cart before the first AJAX call has finished
        // Make sure there is a shipping date
        if (null === $cart->getShippedAt()) {
            $availabilities = $this->getAvailabilities($cart);
            $cart->setShippedAt(new \DateTime(current($availabilities)));
        }

        $this->orderManager->persist($cart);
        $this->orderManager->flush();

        // TODO Find a better way to do this
        $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
        $request->getSession()->set($sessionKeyName, $cart->getId());

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/{cartItemId}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($id, $cartItemId, Request $request, CartContextInterface $cartContext)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $cart = $cartContext->getCart();
        $cartItem = $this->orderItemRepository->find($cartItemId);

        if ($cartItem) {
            $this->orderModifier->removeFromOrder($cart, $cartItem);

            $this->orderManager->persist($cart);
            $this->orderManager->flush();
        }

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurants/map", name="restaurants_map")
     * @Template()
     */
    public function mapAction(Request $request, SlugifyInterface $slugify)
    {
        $restaurants = array_map(function (Restaurant $restaurant) use ($slugify) {
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
                    'slug' => $slugify->slugify($restaurant->getName())
                ])
            ];
        }, $this->getDoctrine()->getRepository(Restaurant::class)->findAll());

        return [
            'restaurants' => $this->get('serializer')->serialize($restaurants, 'json'),
        ];
    }
}
