<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\User;
use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Form\Checkout\Action\AddProductToCartAction as CheckoutAddProductToCart;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCart as AssertAddProductToCart;
use AppBundle\Form\Order\CartType;
use AppBundle\Form\PledgeType;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\Order as OrderConstraint;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Liip\ImagineBundle\Service\FilterService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sonata\SeoBundle\Seo\SeoPageInterface;
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
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 * @HideSoftDeleted
 */
class RestaurantController extends AbstractController
{
    use UserTrait;

    const ITEMS_PER_PAGE = 21;

    private $orderManager;
    private $seoPage;
    private $uploaderHelper;
    private $serializer;
    /**
     * @var OrderTimeHelper
     */
    private OrderTimeHelper $orderTimeHelper;
    /**
     * @var FilterService
     */
    private FilterService $imagineFilter;
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
        SeoPageInterface $seoPage,
        UploaderHelper $uploaderHelper,
        ValidatorInterface $validator,
        RepositoryInterface $productRepository,
        RepositoryInterface $orderItemRepository,
        $orderItemFactory,
        $productVariantResolver,
        RepositoryInterface $productOptionValueRepository,
        $orderItemQuantityModifier,
        $orderModifier,
        OrderTimeHelper $orderTimeHelper,
        SerializerInterface $serializer,
        FilterService $imagineFilter)
    {
        $this->orderManager = $orderManager;
        $this->seoPage = $seoPage;
        $this->uploaderHelper = $uploaderHelper;
        $this->validator = $validator;
        $this->productRepository = $productRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->productVariantResolver = $productVariantResolver;
        $this->productOptionValueRepository = $productOptionValueRepository;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->serializer = $serializer;
        $this->imagineFilter = $imagineFilter;
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

    private function customizeSeoPage(LocalBusiness $restaurant, Request $request)
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
            ->addMeta('property', 'place:location:latitude', (string) $restaurant->getAddress()->getGeo()->getLatitude())
            ->addMeta('property', 'place:location:longitude', (string) $restaurant->getAddress()->getGeo()->getLongitude())
            ;

        $imagePath = $this->uploaderHelper->asset($restaurant, 'imageFile');
        if (null !== $imagePath) {
            $this->seoPage->addMeta('property', 'og:image',
                $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail'));
        }
    }

    private function saveSession(Request $request, OrderInterface $cart)
    {
        // TODO Find a better way to do this
        $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
        $request->getSession()->set($sessionKeyName, $cart->getId());
    }

    private function getContextSlug(LocalBusiness $business)
    {
        return $business->getContext() === Store::class ? 'store' : 'restaurant';
    }

    private function isAnotherRestaurant(OrderInterface $cart, LocalBusiness $restaurant)
    {
        return null !== $cart->getId() && $cart->getRestaurant() !== $restaurant;
    }

    /**
     * @Route("/restaurants", name="restaurants")
     */
    public function listAction(Request $request, LocalBusinessRepository $repository)
    {
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
            $matches = $repository->findAllSorted();
        }

        $count = count($matches);

        $matches = array_slice($matches, $offset, self::ITEMS_PER_PAGE);

        $pages = ceil($count / self::ITEMS_PER_PAGE);

        return $this->render('restaurant/list.html.twig', array(
            'count' => $count,
            'restaurants' => $matches,
            'page' => $page,
            'pages' => $pages,
            'geohash' => $request->query->get('geohash'),
            'addresses_normalized' => $this->getUserAddresses(),
            'address' => $request->query->has('address') ? $request->query->get('address') : null,
            'local_business_context' => $repository->getContext(),
        ));
    }

    /**
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
        IriConverterInterface $iriConverter)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        if (!$restaurant->isEnabled()) {
            if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_RESTAURANT')) {
                throw new NotFoundHttpException();
            }
        }

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

        // This will be used by RestaurantCartContext
        $request->getSession()->set('restaurantId', $id);

        $cart = $cartContext->getCart();

        $isAnotherRestaurant = $this->isAnotherRestaurant($cart, $restaurant);

        if ($isAnotherRestaurant) {
            $cart->clearItems();
            $cart->setShippingTimeRange(null);
            $cart->setRestaurant($restaurant);
        }

        $user = $this->getUser();
        if ($request->query->has('address') && $user && count($user->getAddresses()) > 0) {

            $addressIRI = base64_decode($request->query->get('address'));

            try {

                $shippingAddress = $iriConverter->getItemFromIri($addressIRI);

                if ($user->getAddresses()->contains($shippingAddress)) {
                    $cart->setShippingAddress($shippingAddress);

                    $this->orderManager->persist($cart);
                    $this->orderManager->flush();

                    $this->saveSession($request, $cart);
                }

            } catch (ItemNotFoundException $e) {
                // Do nothing
            }
        }

        $violations = $this->validator->validate($cart);
        $violationCodes = [
            OrderConstraint::SHIPPED_AT_EXPIRED,
            OrderConstraint::SHIPPED_AT_NOT_AVAILABLE
        ];
        if (0 !== count($violations->findByCodes($violationCodes))) {

            $cart->setShippingTimeRange(null);

            if (!$isAnotherRestaurant) {
                $this->orderManager->persist($cart);
                $this->orderManager->flush();
            }
        }

        $cartForm = $this->createForm(CartType::class, $cart);

        if ($request->isMethod('POST')) {

            $cartForm->handleRequest($request);

            $cart = $cartForm->getData();

            // Make sure the shipping address is valid
            // FIXME This is cumbersome, there should be a better way
            $shippingAddress = $cart->getShippingAddress();
            if (null !== $shippingAddress) {
                $isShippingAddressValid = count($this->validator->validate($shippingAddress)) === 0;
                if (!$isShippingAddressValid) {
                    $cart->setShippingAddress(null);
                }
            }

            if ($request->isXmlHttpRequest()) {

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
                if ($isAnotherRestaurant) {

                    return $this->jsonResponse($cart, $errors);
                }

                $this->orderManager->persist($cart);
                $this->orderManager->flush();

                $this->saveSession($request, $cart);

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

        $delay = null;

        Carbon::setLocale($request->attributes->get('_locale'));

        $now = Carbon::now();

        $future = clone $now;
        $future->addMinutes($restaurant->getOrderingDelayMinutes());

        if ($restaurant->getOrderingDelayMinutes() > 0) {
            $delay = $now->diffForHumans($future, ['syntax' => CarbonInterface::DIFF_ABSOLUTE]);
        }

        return $this->render('restaurant/index.html.twig', array(
            'restaurant' => $restaurant,
            'times' => $this->orderTimeHelper->getTimeInfo($cart),
            'delay' => $delay,
            'cart_form' => $cartForm->createView(),
            'addresses_normalized' => $this->getUserAddresses(),
        ));
    }

    /**
     * @Route("/restaurant/{id}/cart/address", name="restaurant_cart_address", methods={"POST"})
     */
    public function changeAddressAction($id, Request $request,
        CartContextInterface $cartContext,
        IriConverterInterface $iriConverter)
    {
        $cart = $cartContext->getCart();
        $user = $this->getUser();
        if ($request->request->has('address') && $user && count($user->getAddresses()) > 0) {

            $addressIRI = $request->request->get('address');

            try {

                $shippingAddress = $iriConverter->getItemFromIri($addressIRI);

                if ($user->getAddresses()->contains($shippingAddress)) {
                    $cart->setShippingAddress($shippingAddress);

                    $this->orderManager->persist($cart);
                    $this->orderManager->flush();
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
     * @Route("/restaurant/{id}/cart/product/{code}", name="restaurant_add_product_to_cart", methods={"POST"})
     */
    public function addProductToCartAction($id, $code, Request $request,
        CartContextInterface $cartContext,
        TranslatorInterface $translator)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $product = $this->productRepository->findOneByCode($code);

        $cart = $cartContext->getCart();

        $action = new CheckoutAddProductToCart();
        $action->restaurant = $restaurant;
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

        if ($action->clear) {
            $cart->clearItems();
            $cart->setShippingTimeRange(null);
            $cart->setRestaurant($restaurant);
        }

        $cartItem = $this->orderItemFactory->createNew();

        if (!$product->hasOptions()) {
            $productVariant = $this->productVariantResolver->getVariant($product);
        } else {

            if (!$request->request->has('options') && !$product->hasNonAdditionalOptions()) {
                $productVariant = $this->productVariantResolver->getVariant($product);
            } else {

                $optionValues = new \SplObjectStorage();
                foreach ($request->request->get('options') as $option) {
                    if (isset($option['code'])) {
                        $optionValue = $this->productOptionValueRepository->findOneByCode($option['code']);
                        if ($optionValue && $product->hasOptionValue($optionValue)) {
                            $quantity = isset($option['quantity']) ? (int) $option['quantity'] : 0;
                            if (!$optionValue->getOption()->isAdditional() || null === $optionValue->getOption()->getValuesRange()) {
                                $quantity = 1;
                            }
                            if ($quantity > 0) {
                                $optionValues->attach($optionValue, $quantity);
                            }
                        }
                    }
                }

                $productVariant = $this->productVariantResolver->getVariantForOptionValues($product, $optionValues);
            }
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        $this->orderItemQuantityModifier->modify($cartItem, $request->request->getInt('quantity', 1));
        $this->orderModifier->addToOrder($cart, $cartItem);

        $this->orderManager->persist($cart);
        $this->orderManager->flush();

        $this->saveSession($request, $cart);

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/clear-time", name="restaurant_cart_clear_time", methods={"POST"})
     */
    public function clearCartTimeAction($id, Request $request, CartContextInterface $cartContext)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $cart = $cartContext->getCart();

        $isAnotherRestaurant = $this->isAnotherRestaurant($cart, $restaurant);

        if ($isAnotherRestaurant) {
            $cart->clearItems();
            $cart->setRestaurant($restaurant);
        }

        $cart->setShippingTimeRange(null);

        if (!$isAnotherRestaurant) {
            $this->orderManager->persist($cart);
            $this->orderManager->flush();

            $this->saveSession($request, $cart);
        }

        $errors = $this->validator->validate($cart);
        $errors = ValidationUtils::serializeViolationList($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{id}/cart/items/{itemId}", name="restaurant_modify_cart_item_quantity", methods={"POST"})
     */
    public function updateCartItemQuantityAction($id, $itemId, Request $request, CartContextInterface $cartContext, OrderProcessorInterface $orderProcessor)
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
    public function removeFromCartAction($id, $cartItemId, Request $request, CartContextInterface $cartContext)
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
    public function mapAction(Request $request, SlugifyInterface $slugify, CacheInterface $appCache)
    {
        $user = $this->getUser();

        if ($user && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
            $cacheKeySuffix = $user->getUsername();
        } else {
            $cacheKeySuffix = 'anonymous';
        }

        $cacheKey = sprintf('homepage.map.%s', $cacheKeySuffix);

        $restaurants = $appCache->get($cacheKey, function (ItemInterface $item) use ($slugify) {

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
            }, $this->getDoctrine()->getRepository(LocalBusiness::class)->findAll());
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
    public function storeListAction(Request $request, LocalBusinessRepository $repository)
    {
        return $this->listAction($request, $repository->withContext(Store::class));
    }
}
