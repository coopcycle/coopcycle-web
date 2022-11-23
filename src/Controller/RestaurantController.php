<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\Cuisine;
use AppBundle\Entity\User;
use AppBundle\Entity\Address;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Event\ItemAddedEvent;
use AppBundle\Event\ItemQuantityChangedEvent;
use AppBundle\Event\ItemRemovedEvent;
use AppBundle\Form\Checkout\Action\AddProductToCartAction as CheckoutAddProductToCart;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCart as AssertAddProductToCart;
use AppBundle\Form\Order\CartType;
use AppBundle\Form\PledgeType;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TimingRegistry;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OptionsPayloadConverter;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\RestaurantFilter;
use AppBundle\Utils\SortableRestaurantIterator;
use AppBundle\Utils\ValidationUtils;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 * @HideSoftDeleted
 */
class RestaurantController extends AbstractController
{
    use UserTrait;

    const ITEMS_PER_PAGE = 21;

    private $orderManager;
    private $serializer;
    private $restaurantFilter;

    /**
     * @var OrderTimeHelper
     */
    private OrderTimeHelper $orderTimeHelper;

    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $validator;

    /**
     * @var RepositoryInterface
     */
    private RepositoryInterface $productRepository;
    private $productVariantResolver;
    private $orderItemFactory;

    /**
     * @var RepositoryInterface
     */
    private RepositoryInterface $orderItemRepository;
    private $orderItemQuantityModifier;
    private $orderModifier;

    public function __construct(
        EntityManagerInterface $orderManager,
        ValidatorInterface $validator,
        RepositoryInterface $productRepository,
        RepositoryInterface $orderItemRepository,
        $orderItemFactory,
        $productVariantResolver,
        $orderItemQuantityModifier,
        $orderModifier,
        OrderTimeHelper $orderTimeHelper,
        SerializerInterface $serializer,
        RestaurantFilter $restaurantFilter)
    {
        $this->orderManager = $orderManager;
        $this->validator = $validator;
        $this->productRepository = $productRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->productVariantResolver = $productVariantResolver;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->serializer = $serializer;
        $this->restaurantFilter = $restaurantFilter;
    }

    private function jsonResponse(OrderInterface $cart, array $errors)
    {
        $country = $this->getParameter('country_iso');

        $serializerContext = [
            'is_web' => true,
            'groups' => ['order', 'address', sprintf('address_%s', $country)]
        ];

        return new JsonResponse([
            'cart'   => $this->serializer->normalize($cart, 'jsonld', $serializerContext),
            'times' => $this->orderTimeHelper->getTimeInfo($cart),
            'errors' => $errors,
        ]);
    }

    private function getContextSlug(LocalBusiness $business)
    {
        return $business->getContext() === Store::class ? 'store' : 'restaurant';
    }

    /**
     * @Route("/restaurants/cuisines/{cuisineName}", name="restaurants_by_cuisine")
     */
    public function listByCuisineAction($cuisineName)
    {
        return $this->redirectToRoute(
            'shops',
            [
                'cuisine' => [$cuisineName]
            ],
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    /**
     * @Route("/restaurants", name="restaurants")
     */
    public function legacyRestaurantsAction(Request $request,
        LocalBusinessRepository $repository,
        CacheInterface $projectCache,
        SlugifyInterface $slugify,
        TimingRegistry $timingRegistry)
    {
        $requestClone = clone $request;

        $requestClone->attributes->set('type', LocalBusiness::getKeyForType(FoodEstablishment::RESTAURANT));

        return $this->listAction($requestClone, $repository, $projectCache, $slugify, $timingRegistry);
    }

    /**
     * @Route("/shops", name="shops")
     */
    public function listAction(Request $request,
        LocalBusinessRepository $repository,
        CacheInterface $projectCache,
        SlugifyInterface $slugify,
        TimingRegistry $timingRegistry)
    {
        $originalParams = $request->query->all();

        $mode = $request->query->get('mode', 'list');

        if (!in_array($mode, ['list', 'map'])) {
            $mode = 'list';
        }

        if ('map' === $mode) {

            return $this->render('restaurant/list_map.html.twig', [
                'geohash' => $request->query->get('geohash'),
                'addresses_normalized' => $this->getUserAddresses(),
            ]);
        }

        // find cuisines which can be selected by user to filter
        $cuisines = $repository->findExistingCuisines();

        $cacheKey = $this->getShopsListCacheKey($request);

        if ($request->query->has('type')) {
            $type = LocalBusiness::getTypeForKey($request->query->get('type'));
            // for filtering we need in query param the full type instead of the key
            $request->query->set('type', $type);
        }

        if ($request->query->has('cuisine')) {
            // filter by cuisine id (index) instead of name
            $cuisineTypes = $request->query->get('cuisine');
            $cuisineIds = [];
            foreach ($cuisines as $cuisine) {
                if (in_array($cuisine->getName(), $cuisineTypes)) {
                    $cuisineIds[] = $cuisine->getId();
                }
            }
            $request->query->set('cuisine', $cuisineIds);
        }

        $restaurantsIds = $projectCache->get($cacheKey, function (ItemInterface $item) use ($repository, $request) {

            $item->expiresAfter(60 * 5);

            return array_map(function (LocalBusiness $restaurant) {

                return $restaurant->getId();
            }, $repository->findByFilters($request->query->all()));
        });

        $matches = array_map(function ($id) use ($repository) {
            return $repository->find($id);
        }, $restaurantsIds);

        $matches = array_values(array_filter($matches));

        if ($request->query->has('geohash') || $request->query->has('address')) {

            $geohash = null;

            if ($request->query->has('geohash') && strlen($request->query->get('geohash')) > 0) {
                $geohash = $request->query->get('geohash');
            } else if ($request->query->has('address') && strlen($request->query->get('address')) > 0) {
                $address = urldecode(base64_decode($request->query->get('address')));

                if (!$address) {
                    return;
                }

                $geohash = json_decode($address)->geohash;
            }

            if (null !== $geohash) {
                $geotools = new Geotools();

                try {

                    $decoded = $geotools->geohash()->decode($geohash);

                    $latitude = $decoded->getCoordinate()->getLatitude();
                    $longitude = $decoded->getCoordinate()->getLongitude();

                    $matches = $this->restaurantFilter->matchingLatLng($matches, $latitude, $longitude);

                } catch (\InvalidArgumentException|\RuntimeException $e) {
                    // Some funny guys may have tried a SQL injection
                }
            }
        }

        $iterator = new SortableRestaurantIterator($matches, $timingRegistry);
        $matches = iterator_to_array($iterator);

        $count = count($matches);

        $page = $request->query->getInt('page', 1);
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        $matches = array_slice($matches, $offset, self::ITEMS_PER_PAGE);

        $pages = ceil($count / self::ITEMS_PER_PAGE);

        $countByType = $repository->countByType();
        $types = array_keys($countByType);

        $request->query->replace($originalParams);

        // AJAX request from filters or pagination
        if ($request->isXmlHttpRequest()) {
            $list = $this->renderView('_partials/restaurant/shops_list.html.twig', [
                'restaurants' => $matches,
                'count' => $count,
            ]);

            $response = new JsonResponse();
            $response->setData(array(
                'rendered_list' => $list,
                'page' => $page,
                'pages' => $pages,
            ));

            return $response;
        }

        return $this->render('restaurant/list.html.twig', array(
            'count' => $count,
            'restaurants' => $matches,
            'page' => $page,
            'pages' => $pages,
            'geohash' => $request->query->get('geohash'),
            'addresses_normalized' => $this->getUserAddresses(),
            'address' => $request->query->get('address'),
            'types' => $types,
            'cuisines' => $cuisines,
        ));
    }

    /**
     * @Route("/hub/{id}-{slug}", name="hub",
     *   requirements={
     *     "id"="(\d+)",
     *     "slug"="([a-z0-9-]+)"
     *   },
     *   defaults={
     *     "slug"=""
     *   }
     * )
     */
    public function hubAction($id, $slug, Request $request,
        SlugifyInterface $slugify)
    {
        $hub = $this->getDoctrine()->getRepository(Hub::class)->find($id);

        if (!$hub) {
            throw new NotFoundHttpException();
        }

        $expectedSlug = $slugify->slugify($hub->getName());
        $redirectToCanonicalRoute = $slug !== $expectedSlug;

        if ($redirectToCanonicalRoute) {

            return $this->redirectToRoute('hub', [
                'id' => $id,
                'slug' => $expectedSlug,
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('restaurant/hub.html.twig', [
            'hub' => $hub,
            'business_type_filter' => $request->query->get('type'),
        ]);
    }

    /**
     * @param string $type
     * @param int $id
     * @param string $slug
     * @param Request $request
     * @param SlugifyInterface $slugify
     * @param CartContextInterface $cartContext
     * @param Address|null $address
     *
     * @Route("/{type}/{id}-{slug}", name="restaurant",
     *   requirements={
     *     "type"="(restaurant|store)",
     *     "id"="(\d+|__RESTAURANT_ID__)",
     *     "slug"="([a-z0-9-]+)"
     *   },
     *   defaults={
     *     "slug"="",
     *     "type"="restaurant"
     *   }
     * )
     */
    public function indexAction($type, $id, $slug, Request $request,
        SlugifyInterface $slugify,
        CartContextInterface $cartContext,
        RestaurantResolver $restaurantResolver,
        Address $address = null)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted('view', $restaurant);

        $contextSlug = $this->getContextSlug($restaurant);
        $expectedSlug = $slugify->slugify($restaurant->getName());

        $redirectToCanonicalRoute = ($contextSlug !== $type) || ($slug !== $expectedSlug);

        if ($redirectToCanonicalRoute) {

            return $this->redirectToRoute('restaurant', [
                'id' => $id,
                'slug' => $expectedSlug,
                'type' => $contextSlug,
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        if ($restaurant->getState() === LocalBusiness::STATE_PLEDGE) {

            $numberOfVotes = count($restaurant->getPledge()->getVotes());

            $user = $this->getUser();
            $checkVote = $user !== null ? $restaurant->getPledge()->hasVoted($this->getUser()) : false;

            return $this->render('restaurant/restaurant_pledge_accepted.html.twig', [
                'restaurant' => $restaurant,
                'number_of_votes' => $numberOfVotes,
                'has_already_voted' => $checkVote
            ]);
        }

        $cart = $cartContext->getCart();

        if (null !== $address) {
            $cart->setShippingAddress($address);

            $this->orderManager->persist($cart);
            $this->orderManager->flush();
        }

        // This is useful to "cleanup" a cart that was stored
        // with a time range that is now expired
        // FIXME Maybe this should be moved to a Doctrine postLoad listener?
        $violations = $this->validator->validate($cart, null, ['ShippingTime']);
        if (count($violations) > 0) {

            $cart->setShippingTimeRange(null);

            if ($restaurantResolver->accept($cart)) {
                $this->orderManager->persist($cart);
                $this->orderManager->flush();
            }
        }

        $cartForm = $this->createForm(CartType::class, $cart);

        if ($request->isMethod('POST')) {

            $cartForm->handleRequest($request);

            // The cart is valid, and the user clicked on the submit button
            if ($cartForm->isValid()) {

                $this->orderManager->flush();

                return $this->redirectToRoute('order');
            }
        }

        return $this->render('restaurant/index.html.twig', array(
            'restaurant' => $restaurant,
            'times' => $this->orderTimeHelper->getTimeInfo($cart),
            'cart_form' => $cartForm->createView(),
            'addresses_normalized' => $this->getUserAddresses(),
        ));
    }

    /**
     * @Route("/restaurant/{id}/cart/address", name="restaurant_cart_address", methods={"POST"})
     */
    public function changeAddressAction($id, Request $request,
        CartContextInterface $cartContext,
        IriConverterInterface $iriConverter,
        RestaurantResolver $restaurantResolver)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        $cart = $cartContext->getCart();

        $user = $this->getUser();
        if ($request->request->has('address') && $user && count($user->getAddresses()) > 0) {

            $addressIRI = $request->request->get('address');

            try {

                $shippingAddress = $iriConverter->getItemFromIri($addressIRI);

                if ($user->getAddresses()->contains($shippingAddress)) {
                    $cart->setShippingAddress($shippingAddress);
                    $cart->setTakeaway(false);

                    if ($restaurantResolver->accept($cart)) {
                        $this->orderManager->persist($cart);
                        $this->orderManager->flush();
                    }
                }

            } catch (ItemNotFoundException $e) {
                // Do nothing
            }
        }

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart", name="restaurant_cart", methods={"POST"})
     */
    public function cartAction($id, Request $request,
        CartContextInterface $cartContext,
        RestaurantResolver $restaurantResolver)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted('view', $restaurant);

        $cart = $cartContext->getCart();

        // This is useful to "cleanup" a cart that was stored
        // with a time range that is now expired
        // FIXME Maybe this should be moved to a Doctrine postLoad listener?
        $violations = $this->validator->validate($cart, null, ['ShippingTime']);
        if (count($violations) > 0) {

            $cart->setShippingTimeRange(null);

            if ($restaurantResolver->accept($cart)) {
                $this->orderManager->persist($cart);
                $this->orderManager->flush();
            }
        }

        $cartForm = $this->createForm(CartType::class, $cart);

        $cartForm->handleRequest($request);

        $cart = $cartForm->getData();

        $errors = [];

        if (!$cartForm->isValid()) {
            foreach ($cartForm->getErrors() as $formError) {
                $propertyPath = (string) $formError->getOrigin()->getPropertyPath();
                $errors[$propertyPath] = [ ValidationUtils::serializeFormError($formError) ];
            }
        }

        // Customer may be browsing the available restaurants
        // Make sure the request targets the same restaurant
        // If not, we don't persist the cart
        if ($restaurantResolver->accept($cart)) {
            $this->orderManager->persist($cart);
            $this->orderManager->flush();
        }

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/product/{code}", name="restaurant_add_product_to_cart", methods={"POST"})
     */
    public function addProductToCartAction($id, $code, Request $request,
        CartContextInterface $cartContext,
        TranslatorInterface $translator,
        RestaurantResolver $restaurantResolver,
        OptionsPayloadConverter $optionsPayloadConverter)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $product = $this->productRepository->findOneByCode($code);

        $cart = $cartContext->getCart();

        $action = new CheckoutAddProductToCart();
        $action->product = $product;
        $action->cart = $cart;
        $action->clear = $request->request->getBoolean('_clear', false);

        $violations = $this->validator->validate($action, new AssertAddProductToCart());

        if (count($violations) > 0) {

            $errors = [];
            foreach ($violations as $violation) {
                $key = $violation->getPropertyPath();
                $errors[$key][] = [
                    'message' => $violation->getMessage()
                ];
            }

            return $this->jsonResponse($cart, $errors);
        }

        $cartItem = $this->orderItemFactory->createNew();

        if (!$product->hasOptions()) {
            $productVariant = $this->productVariantResolver->getVariant($product);
        } else {
            if (!$request->request->has('options') && !$product->hasNonAdditionalOptions()) {
                $productVariant = $this->productVariantResolver->getVariant($product);
            } else {
                $optionValues = $optionsPayloadConverter->convert($product, $request->request->get('options'));
                $productVariant = $this->productVariantResolver->getVariantForOptionValues($product, $optionValues);
            }
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        $this->orderItemQuantityModifier->modify($cartItem, $request->request->getInt('quantity', 1));
        $this->orderModifier->addToOrder($cart, $cartItem);

        $this->orderManager->persist($cart);
        $this->orderManager->flush();

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/clear-time", name="restaurant_cart_clear_time", methods={"POST"})
     */
    public function clearCartTimeAction($id, Request $request,
        CartContextInterface $cartContext,
        RestaurantResolver $restaurantResolver)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $cart = $cartContext->getCart();

        $cart->setShippingTimeRange(null);

        if ($restaurantResolver->accept($cart)) {
            $this->orderManager->persist($cart);
            $this->orderManager->flush();
        }

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/items/{itemId}", name="restaurant_modify_cart_item_quantity", methods={"POST"})
     */
    public function updateCartItemQuantityAction($id, $itemId, Request $request,
        CartContextInterface $cartContext,
        OrderProcessorInterface $orderProcessor)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $cart = $cartContext->getCart();

        $cartItem = $this->orderItemRepository->find($itemId);

        if (!$cart->getItems()->contains($cartItem)) {
            $errors = $this->validator->validate($cart);
            $errors = ValidationUtils::serializeViolationList($errors);

            return $this->jsonResponse($cart, $errors);
        }

        $quantity = $request->request->getInt('quantity', 1);
        $this->orderItemQuantityModifier->modify($cartItem, $quantity);

        $orderProcessor->process($cart);

        $this->orderManager->persist($cart);
        $this->orderManager->flush();

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/{cartItemId}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($id, $cartItemId, Request $request,
        CartContextInterface $cartContext)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $cart = $cartContext->getCart();
        $cartItem = $this->orderItemRepository->find($cartItemId);

        if ($cartItem) {
            $this->orderModifier->removeFromOrder($cart, $cartItem);

            $this->orderManager->persist($cart);
            $this->orderManager->flush();
        }

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }


    /**
     * @Route("/restaurants/map", name="restaurants_map")
     */
    public function mapAction(Request $request, SlugifyInterface $slugify, CacheInterface $projectCache)
    {
        $restaurants = $projectCache->get('homepage.map', function (ItemInterface $item) use ($slugify) {

            $item->expiresAfter(60 * 30);

            return array_map(function (LocalBusiness $restaurant) use ($slugify) {

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
            }, $this->getDoctrine()->getRepository(LocalBusiness::class)->findBy(['enabled' => true]));
        });

        return $this->render('restaurant/map.html.twig', [
            'restaurants' => $this->serializer->serialize($restaurants, 'json'),
        ]);
    }

    /**
     * @Route("/restaurants/suggest", name="restaurants_suggest")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function suggestRestaurantAction(Request $request,
        EntityManagerInterface $manager,
        EmailManager $emailManager,
        SettingsManager $settingsManager,
        TranslatorInterface $translator)
    {
        if ('yes' !== $settingsManager->get('enable_restaurant_pledges')) {
            throw new NotFoundHttpException();
        }

        $pledge = new Pledge();

        $form = $this->createForm(PledgeType::class, $pledge);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $pledge->setState('new');
            $pledge->setUser($this->getUser());

            $manager->persist($pledge);
            $manager->flush();

            $emailManager->sendTo(
                $emailManager->createAdminPledgeConfirmationMessage($pledge),
                $settingsManager->get('administrator_email')
            );

            $this->addFlash(
                'pledge',
                $translator->trans('form.suggest.thank_you_message')
            );

            return $this->redirectToRoute('restaurants_suggest');
        }

        return $this->render('restaurant/restaurant_pledge.html.twig', [
            'form_pledge' => $form->createView()
        ]);
    }

    /**
     * @Route("/restaurant/{id}/vote", name="restaurant_vote", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function voteAction($id, SettingsManager $settingsManager)
    {
        if ('yes' !== $settingsManager->get('enable_restaurant_pledges')) {
            throw new NotFoundHttpException();
        }

        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $user = $this->getUser();

        if ($restaurant->getPledge() !== null) {
            $restaurant->getPledge()->addVote($user);
            $this->orderManager->flush();
        }

        return $this->redirectToRoute('restaurant', [ 'id' => $id ]);
    }

    /**
     * @Route("/stores", name="stores")
     */
    public function legacyStoreListAction(Request $request,
        LocalBusinessRepository $repository,
        CacheInterface $projectCache,
        SlugifyInterface $slugify,
        TimingRegistry $timingRegistry)
    {
        $requestClone = clone $request;

        $requestClone->attributes->set('type', LocalBusiness::getKeyForType(Store::GROCERY_STORE));

        return $this->listAction($requestClone, $repository, $projectCache, $slugify, $timingRegistry);
    }

    /**
     * @Route("/restaurants/tags/{tags}", name="restaurants_by_tags")
     */
    public function listByTagsAction($tags)
    {
        return $this->redirectToRoute(
            'shops',
            [
                'category' => 'zerowaste'
            ],
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    /**
     * The cache key is built with all query params alphabetically sorted.
     * With this function we make sure that same filters in different order represent the same cache key.
     */
    private function getShopsListCacheKey($request)
    {
        $parameters = [
            'category',
            'cuisine',
            'type',
        ];

        sort($parameters);

        $query = [];
        foreach ($parameters as $parameter) {
            $query[$parameter] = $request->query->get($parameter);
        }

        if (isset($query['cuisine'])) {
            sort($query['cuisine']);
        }

        $cacheKey = http_build_query($query);

        return sprintf('shops.list.filters|%s', $cacheKey);
    }
}
