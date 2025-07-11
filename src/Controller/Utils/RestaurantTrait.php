<?php

namespace AppBundle\Controller\Utils;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\GetCollection;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use AppBundle\Edenred\SynchronizerClient;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Cuisine;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant\PreparationTimeRule;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductImage;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxonRepository;
use AppBundle\Form\ApiAppType;
use AppBundle\Form\ClosingRuleType;
use AppBundle\Form\MenuEditorType;
use AppBundle\Form\MenuTaxonType;
use AppBundle\Form\MenuType;
use AppBundle\Form\PreparationTimeRulesType;
use AppBundle\Form\ProductOptionType;
use AppBundle\Form\ProductType;
use AppBundle\Form\Restaurant\DepositRefundSettingsType;
use AppBundle\Form\Restaurant\ReusablePackagingType;
use AppBundle\Form\RestaurantType;
use AppBundle\Form\ReusablePackagingChoiceLoader;
use AppBundle\Form\Sylius\Promotion\ItemsTotalBasedPromotionType;
use AppBundle\Form\Sylius\Promotion\OfferDeliveryType;
use AppBundle\Form\Type\ProductTaxCategoryChoiceType;
use AppBundle\LoopEat\Client as LoopeatClient;
use AppBundle\Message\CopyProducts;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Taxation\TaxesHelper;
use AppBundle\Utils\MenuEditor;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\RestaurantStats;
use AppBundle\Utils\ValidationUtils;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ObjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use League\Csv\Writer as CsvWriter;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use MercadoPago;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Product\Model\ProductTranslation;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Handler\UploadHandler;

trait RestaurantTrait
{
    abstract protected function getRestaurantRoutes();

    protected function getRestaurantRoute($name)
    {
        $routes = $this->getRestaurantRoutes();

        return $routes[$name];
    }

    protected function withRoutes($params, array $routes = [])
    {
        $routes = array_merge($routes, $this->getRestaurantRoutes());

        $routeParams = [];
        foreach ($routes as $key => $value) {
            $routeParams[sprintf('%s_route', $key)] = $value;
        }

        return array_merge($params, $routeParams);
    }

    protected function renderRestaurantForm(LocalBusiness $restaurant, Request $request,
        ValidatorInterface $validator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        TranslatorInterface $translator,
        LoopeatClient $loopeatClient)
    {
        $form = $this->createForm(RestaurantType::class, $restaurant, [
            'loopeat_enabled' => $this->getParameter('loopeat_enabled'),
            'edenred_enabled' => $this->getParameter('edenred_enabled'),
            'vytal_enabled' => $this->getParameter('vytal_enabled'),
            'en_boite_le_plat_enabled' => $this->getParameter('en_boite_le_plat_enabled'),
            'dabba_enabled' => $this->getParameter('dabba_enabled'),
        ]);

        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $request->getSession();

        // Associate Stripe account with restaurant
        if ($session->getFlashBag()->has('stripe_account')) {
            $messages = $session->getFlashBag()->get('stripe_account');
            if (!empty($messages)) {
                foreach ($messages as $stripeAccountId) {
                    $stripeAccount = $this->entityManager
                        ->getRepository(StripeAccount::class)
                        ->find($stripeAccountId);
                    if ($stripeAccount) {
                        $restaurant->addStripeAccount($stripeAccount);
                        $this->entityManager->flush();

                        $this->addFlash(
                            'notice',
                            $translator->trans('form.local_business.stripe_account.success')
                        );
                    }
                }
            }
        }

        $wasDepositRefundEnabled = $restaurant->isDepositRefundEnabled();
        $wasVytalEnabled = $restaurant->isVytalEnabled();
        $wasDabbaEnabled = $restaurant->isDabbaEnabled();

        $activationErrors = [];
        $formErrors = [];
        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            if ($form->isValid()) {
                $restaurant = $form->getData();

                if ($form->getClickedButton() && 'delete' === $form->getClickedButton()->getName()) {

                    $this->entityManager->remove($restaurant);
                    $this->entityManager->flush();

                    return $this->redirectToRoute($routes['restaurants']);
                }

                if ($restaurant->getId() === null && !$this->getUser()->hasRole('ROLE_ADMIN')) {
                    $this->getUser()->addRestaurant($restaurant);
                }

                // Make sure the restaurant can be enabled, or disable it
                $violations = $validator->validate($restaurant, null, ['activable']);
                if (count($violations) > 0) {
                    $restaurant->setEnabled(false);
                }

                // For Loopeat, ReusablePackaging instances
                // will be created via ReusablePackagingChoiceLoader

                if (!$wasVytalEnabled && $restaurant->isVytalEnabled()) {

                    if (!$restaurant->hasReusablePackagingWithName('Vytal')) {
                        $reusablePackaging = new ReusablePackaging();
                        $reusablePackaging->setName('Vytal');
                        $reusablePackaging->setType(ReusablePackaging::TYPE_VYTAL);
                        $reusablePackaging->setPrice(0);
                        $reusablePackaging->setOnHold(0);
                        $reusablePackaging->setOnHand(9999);
                        $reusablePackaging->setTracked(false);

                        $restaurant->addReusablePackaging($reusablePackaging);
                    }
                }

                if (!$wasDabbaEnabled && $restaurant->isDabbaEnabled()) {

                    if (!$restaurant->hasReusablePackagingWithName('Dabba')) {
                        $reusablePackaging = new ReusablePackaging();
                        $reusablePackaging->setName('Dabba');
                        $reusablePackaging->setType(ReusablePackaging::TYPE_DABBA);
                        $reusablePackaging->setPrice(0);
                        $reusablePackaging->setOnHold(0);
                        $reusablePackaging->setOnHand(9999);
                        $reusablePackaging->setTracked(false);

                        $restaurant->addReusablePackaging($reusablePackaging);
                    }
                }

                $this->entityManager->persist($restaurant);
                $this->entityManager->flush();

                if (!$wasDepositRefundEnabled && $restaurant->isDepositRefundEnabled()) {
                    $this->addFlash(
                        'notice',
                        $translator->trans('confirm.deposit_refund_enabled', [
                            '%url%' => $this->generateUrl($this->getRestaurantRoute('deposit_refund'), ['id' => $restaurant->getId()])
                        ])
                    );
                } else {
                    $this->addFlash(
                        'notice',
                        $translator->trans('global.changesSaved')
                    );
                }

                return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
            } else {
                $violations = new ConstraintViolationList();
                foreach ($form->getErrors(true) as $error) {
                    $violations->add($error->getCause());
                }
                $formErrors = ValidationUtils::serializeValidationErrors($violations);
            }

        } else {
            $violations = $validator->validate($restaurant, null, ['activable']);
            $activationErrors = ValidationUtils::serializeValidationErrors($violations);
        }



        $loopeatAuthorizeUrl = '';
        if ($this->getParameter('loopeat_enabled') && $restaurant->isLoopeatEnabled()) {

            $redirectUri = $this->generateUrl('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $redirectAfterUri = $this->generateUrl(
                $routes['success'],
                ['id' => $restaurant->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Use a JWT as the "state" parameter
            $state = $jwtEncoder->encode([
                'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
                'sub' => $iriConverter->getIriFromResource($restaurant),
                // The "iss" (Issuer) claim contains a redirect URL
                'iss' => $redirectAfterUri,
            ]);

            $params = [
                'state' => $state,
                'redirect_uri' => $redirectUri,
            ];

            $loopeatAuthorizeUrl = $loopeatClient->getRestaurantOAuthAuthorizeUrl($params);
        }

        $cuisines = $this->entityManager->getRepository(Cuisine::class)->findAll();

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'restaurant' => $restaurant,
            'activationErrors' => $activationErrors,
            'formErrors' => $formErrors,
            'form' => $form->createView(),
            'layout' => $request->attributes->get('layout'),
            'loopeat_authorize_url' => $loopeatAuthorizeUrl,
            'cuisines' => $this->normalizer->normalize($cuisines, 'json', ['groups' => ['restaurant']]),
        ], $routes));
    }

    public function restaurantAction($id, Request $request,
        ValidatorInterface $validator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        TranslatorInterface $translator,
        LoopeatClient $loopeatClient)
    {
        $repository = $this->entityManager->getRepository(LocalBusiness::class);

        $restaurant = $repository->find($id);

        $this->accessControl($restaurant);

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {
            $restaurantNormalized = $this->normalizer->normalize($restaurant, 'jsonld', [
                'groups' => ['restaurant']
            ]);

            return new JsonResponse($restaurantNormalized);
        }

        return $this->renderRestaurantForm($restaurant, $request, $validator, $jwtEncoder, $iriConverter, $translator, $loopeatClient);
    }

    public function newRestaurantAction(Request $request,
        ValidatorInterface $validator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        TranslatorInterface $translator,
        LoopeatClient $loopeatClient)
    {
        // TODO Check roles
        $restaurant = new LocalBusiness();
        $restaurant->setContract(new Contract());

        return $this->renderRestaurantForm($restaurant, $request, $validator, $jwtEncoder, $iriConverter, $translator, $loopeatClient);
    }

    public function restaurantNewAdhocOrderAction($restaurantId, Request $request,
        JWTTokenManagerInterface $jwtManager)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $form = $this->createFormBuilder()
            ->add('taxCategory', ProductTaxCategoryChoiceType::class)
            ->getForm();

        $view = $form->get('taxCategory')->createView();

        $taxCategories = [];
        foreach($view->vars['choices'] as $taxCategoryView) {
            $taxCategories[] = [
                'name' => $taxCategoryView->label,
                'code' => $taxCategoryView->value,
            ];
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant_normalized' => $this->normalizer->normalize($restaurant, 'jsonld', [
                'groups' => ['restaurant']
            ]),
            'restaurant' => $restaurant,
            'jwt' => $jwtManager->create($this->getUser()),
            'taxCategories' => $taxCategories,
        ], []));
    }

    public function restaurantDashboardAction($restaurantId, Request $request,
        EntityManagerInterface $entityManager,
        IriConverterInterface $iriConverter,
        AuthorizationCheckerInterface $authorizationChecker)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $date = new \DateTime('now');
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }

        if ($request->query->has('order')) {
            $order = $request->query->get('order');
            if (is_numeric($order)) {

                return $this->redirectToRoute($request->attributes->get('_route'), [
                    'restaurantId' => $restaurant->getId(),
                    'date' => $date->format('Y-m-d'),
                    'order' => $iriConverter->getIriFromResource(Order::class, context: ['uri_variables' => ['id' => $order]])
                ], 301);
            }
        }

        $start = clone $date;
        $end = clone $date;

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        // FIXME
        // Ideally, $authorizationChecker should be injected
        // into OrderRepository directly, but it seems impossible with Sylius dependency injection
        $orders = $entityManager->getRepository(Order::class)
            ->findOrdersByRestaurantAndDateRange($restaurant, $start, $end, $authorizationChecker->isGranted('ROLE_ADMIN'));

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'restaurant_normalized' => $this->normalizer->normalize($restaurant, 'jsonld', [
                'groups' => ['restaurant']
            ]),
            'orders_normalized' => $this->normalizer->normalize($orders, 'jsonld', [
                'resource_class' => Order::class,
                'operation' => new GetCollection(),
                'groups' => ['order_minimal']
            ]),
            'initial_order' => $request->query->get('order'),
            'routes' => $routes,
            'date' => $date,
            'adhoc_order_enabled' => $this->adhocOrderEnabled && $restaurant->belongsToHub(),
        ], $routes));
    }

    public function restaurantMenuTaxonsAction($id, Request $request, FactoryInterface $taxonFactory)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $routes = $request->attributes->get('routes');
        $menus = $restaurant->getTaxons();

        $forms = [];
        foreach ($menus as $menu) {
            $forms[$menu->getId()] = $this->createForm(MenuTaxonType::class, $menu)->createView();
        }

        $form = $this->createFormBuilder()
            ->add('name', TextType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $name = $form->get('name')->getData();

            $menuTaxon = $taxonFactory->createNew();

            $uuid = Uuid::uuid1()->toString();

            $menuTaxon->setCode($uuid);
            $menuTaxon->setSlug($uuid);
            $menuTaxon->setName($name);

            $restaurant->addTaxon($menuTaxon);

            $this->entityManager->flush();

            return $this->redirectToRoute($routes['menu_taxon'], [
                'restaurantId' => $restaurant->getId(),
                'menuId' => $menuTaxon->getId()
            ]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'menus' => $menus,
            'restaurant' => $restaurant,
            'forms' => $forms,
            'form' => $form->createView(),
        ], $routes));
    }

    public function activateRestaurantMenuTaxonAction($restaurantId, $menuId, Request $request,
        TaxonRepository $taxonRepository,
        TranslatorInterface $translator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $menuTaxon = $taxonRepository
            ->find($menuId);

        $restaurant->setMenuTaxon($menuTaxon);

        $this->entityManager->flush();

        $this->addFlash(
            'notice',
            $translator->trans('restaurant.menus.activated', ['%menu_name%' => $menuTaxon->getName()])
        );

        $routes = $request->attributes->get('routes');

        return $this->redirectToRoute($routes['menu_taxons'], [
            'id' => $restaurant->getId(),
        ]);
    }


    public function deleteRestaurantMenuTaxonChildAction($restaurantId, $menuId, $sectionId, Request $request,
        TaxonRepository $taxonRepository,
        EntityManagerInterface $entityManager)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $menuTaxon = $taxonRepository->find($menuId);
        $toRemove = $taxonRepository->find($sectionId);

        $menuTaxon->removeChild($toRemove);

        $entityManager->flush();

        $routes = $request->attributes->get('routes');

        return $this->redirectToRoute($routes['menu_taxon'], [
            'restaurantId' => $restaurant->getId(),
            'menuId' => $menuTaxon->getId()
        ]);
    }

    #[HideSoftDeleted]
    public function restaurantMenuTaxonAction($restaurantId, $menuId, Request $request,
        TaxonRepository $taxonRepository,
        FactoryInterface $taxonFactory,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher,
        TranslatorInterface $translator)
    {
        $routes = $request->attributes->get('routes');

        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $menuTaxon = $taxonRepository
            ->find($menuId);

        // Handle deletion
        $menuForm = $this->createForm(MenuTaxonType::class, $menuTaxon);
        $menuForm->handleRequest($request);
        if ($menuForm->isSubmitted() && $menuForm->isValid()) {
            if ($menuForm->getClickedButton() && 'delete' === $menuForm->getClickedButton()->getName()) {

                $restaurant->removeTaxon($menuTaxon);
                $entityManager->remove($menuTaxon);

                $entityManager->flush();

                return $this->redirectToRoute($routes['menu_taxons'], ['id' => $restaurantId]);
            }
        }

        $form = $this->createFormBuilder()
            ->add('name', TextType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $name = $form->get('name')->getData();

            $uuid = Uuid::uuid1()->toString();

            $child = $taxonFactory->createNew();
            $child->setCode($uuid);
            $child->setSlug($uuid);
            $child->setName($name);

            $menuTaxon->addChild($child);
            $entityManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        $menuEditor = new MenuEditor($restaurant, $menuTaxon);
        $menuEditorForm = $this->createForm(MenuEditorType::class, $menuEditor);

        $originalTaxonProducts = new \SplObjectStorage();
        foreach ($menuEditor->getChildren() as $child) {
            $taxonProducts = new ArrayCollection();
            foreach ($child->getTaxonProducts() as $taxonProduct) {
                $taxonProducts->add($taxonProduct);
            }

            $originalTaxonProducts[$child] = $taxonProducts;
        }

        // This will be used to determine if sections have been reordered
        $originalSectionPositions = [];
        foreach ($menuEditor->getChildren() as $child) {
            $originalSectionPositions[$child->getPosition()] = $child->getId();
        }
        ksort($originalSectionPositions);
        $originalSectionPositions = array_values($originalSectionPositions);

        $menuEditorForm->handleRequest($request);
        if ($menuEditorForm->isSubmitted() && $menuEditorForm->isValid()) {

            $menuEditor = $menuEditorForm->getData();

            $newSectionPositions = [];

            foreach ($menuEditor->getChildren() as $child) {

                // The section is empty
                if (count($originalTaxonProducts[$child]) > 0 && count($child->getTaxonProducts()) === 0) {
                    foreach ($originalTaxonProducts[$child] as $originalTaxonProduct) {
                        $originalTaxonProducts[$child]->removeElement($originalTaxonProduct);
                        $entityManager->remove($originalTaxonProduct);
                    }
                    continue;
                }

                $newSectionPositions[$child->getPosition()] = $child->getId();

                foreach ($child->getTaxonProducts() as $taxonProduct) {

                    $taxonProduct->setTaxon($child);

                    foreach ($originalTaxonProducts[$child] as $originalTaxonProduct) {
                        if (!$child->getTaxonProducts()->contains($originalTaxonProduct)) {
                            $child->getTaxonProducts()->removeElement($originalTaxonProduct);
                            $entityManager->remove($originalTaxonProduct);
                        }
                    }
                }
            }

            ksort($newSectionPositions);
            $newSectionPositions = array_values($newSectionPositions);

            if ($originalSectionPositions !== $newSectionPositions) {
                $taxonRepository->reorder($menuTaxon, 'position');
            }

            $entityManager->flush();

            if ($restaurant->getMenuTaxon() === $menuTaxon) {
                $dispatcher->dispatch(new GenericEvent($restaurant), 'catalog.updated');
            }

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'menu' => $menuTaxon,
            'form' => $form->createView(),
            'menu_editor_form' => $menuEditorForm->createView(),
        ], $routes));
    }

    public function restaurantPlanningAction($id, Request $request, TranslatorInterface $translator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $form = $this->createForm(ClosingRuleType::class);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $form->handleRequest($request);

        $routes = $request->attributes->get('routes');

        if ($form->isSubmitted() && $form->isValid()) {
            $closingRule = $form->getData();
            $restaurant->addClosingRule($closingRule);

            $this->entityManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );
            return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'closing_rules_json' => $this->serializer->serialize($restaurant->getClosingRules(), 'json', ['groups' => ['planning']]),
            'restaurant' => $restaurant,
            'routes' => $routes,
            'form' => $form->createView()
        ], $routes));
    }

    private function createRestaurantProductForm(LocalBusiness $restaurant, ProductInterface $product, LoopeatClient $loopeatClient, EntityManagerInterface $entityManager)
    {
        return $this->createForm(ProductType::class, $product, [
            'owner' => $restaurant,
            'with_reusable_packaging' =>
                $restaurant->isDepositRefundEnabled() || $restaurant->isLoopeatEnabled() || $restaurant->isDabbaEnabled(),
            'reusable_packaging_choice_loader' => new ReusablePackagingChoiceLoader($restaurant, $loopeatClient, $entityManager),
            'options_loader' => function (ProductInterface $product) use ($restaurant) {

                $opts = [];
                foreach ($restaurant->getProductOptions() as $opt) {
                    $opts[] = [
                        'product'  => $product,
                        'option'   => $opt,
                        'position' => $product->getPositionForOption($opt)
                    ];
                }

                uasort($opts, function ($a, $b) {
                    if ($a['position'] === $b['position']) return 0;
                    if ($a['position'] === -1) return 1;
                    if ($b['position'] === -1) return -1;
                    return $a['position'] < $b['position'] ? -1 : 1;
                });

                return $opts;
            }
        ]);
    }

    #[HideSoftDeleted]
    public function restaurantProductsAction($id, Request $request,
        IriConverterInterface $iriConverter,
        PaginatorInterface $paginator,
        TranslatorInterface $translator,
        MessageBusInterface $messageBus)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $qb = $this->entityManager
            ->getRepository(Product::class)
            ->createQueryBuilder('p');

        $qb->innerJoin(ProductTranslation::class, 't', Expr\Join::WITH, 't.translatable = p.id');
        $qb->andWhere('p.restaurant = :restaurant');
        $qb->setParameter('restaurant', $restaurant);

        $products = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 't.name',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'asc',
                PaginatorInterface::SORT_FIELD_ALLOW_LIST => ['t.name'],
            ]
        );

        $routes = $request->attributes->get('routes');

        $copyForm = $this->createFormBuilder()
            ->add('restaurant', ChoiceType::class, [
                'choice_loader' => new CallbackChoiceLoader(function () use ($restaurant) {
                    $otherRestaurants = $this->entityManager
                        ->getRepository(LocalBusiness::class)
                        ->findOthers($restaurant);

                    $choices = [];
                    foreach ($otherRestaurants as $otherRestaurant) {
                        $choices[$otherRestaurant['name']] = $otherRestaurant['id'];
                    }

                    return $choices;
                })
            ])
            ->getForm();

        $copyForm->handleRequest($request);
        if ($copyForm->isSubmitted() && $copyForm->isValid()) {

            $destId = $copyForm->get('restaurant')->getData();

            $dest = $this->entityManager
                ->getRepository(LocalBusiness::class)
                ->find($destId);

            if (count($dest->getProducts()) > 0) {

                $this->addFlash(
                    'error',
                    $translator->trans('restaurant.copy_products.not_empty')
                );

                return $this->redirectToRoute($routes['products'], ['id' => $id]);
            }

            // Run this asynchronously, because it may be long
            $messageBus->dispatch(new CopyProducts($id, $destId));

            $this->addFlash(
                'notice',
                $translator->trans('restaurant.copy_products.copying')
            );

            return $this->redirectToRoute($routes['products'], ['id' => $destId]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'products' => $products,
            'restaurant' => $restaurant,
            'restaurant_iri' => $iriConverter->getIriFromResource($restaurant),
            'copy_form' => $copyForm->createView(),
        ], $routes));
    }

    public function restaurantProductAction($restaurantId, $productId, Request $request,
        ObjectRepository $productRepository,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher,
        LoopeatClient $loopeatClient)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $product = $productRepository
            ->find($productId);

        $form =
            $this->createRestaurantProductForm($restaurant, $product, $loopeatClient, $entityManager);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $product = $form->getData();

            if (!$product->isReusablePackagingEnabled()) {
                $product->clearReusablePackagings();
            }

            if ($form->getClickedButton()) {
                if ('delete' === $form->getClickedButton()->getName()) {
                    $entityManager->remove($product);
                }
            }

            $entityManager->flush();

            $dispatcher->dispatch(new GenericEvent($restaurant), 'catalog.updated');

            return $this->redirectToRoute($routes['products'], ['id' => $restaurantId]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'product' => $product,
            'form' => $form->createView()
        ], $routes));
    }

    public function newRestaurantProductAction($id, Request $request,
        FactoryInterface $productFactory,
        EntityManagerInterface $entityManager,
        LoopeatClient $loopeatClient)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $product = $productFactory
            ->createNew();

        $product->setEnabled(false);

        $form =
            $this->createRestaurantProductForm($restaurant, $product, $loopeatClient, $entityManager);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $product = $form->getData();

            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute($routes['products'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'product' => $product,
            'form' => $form->createView()
        ], $routes));
    }

    #[HideSoftDeleted]
    public function restaurantProductOptionsAction($id, Request $request)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'options' => $restaurant->getProductOptions(),
            'restaurant' => $restaurant,
        ], $routes));
    }

    public function restaurantProductOptionAction($restaurantId, $optionId, Request $request,
        ProductOptionRepositoryInterface $productOptionRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator)
    {
        $filterCollection = $entityManager->getFilters();
        if ($filterCollection->isEnabled('disabled_filter')) {
            $filterCollection->disable('disabled_filter');
        }

        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $productOption = $productOptionRepository
            ->find($optionId);

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            if ($form->getClickedButton() && 'delete' === $form->getClickedButton()->getName()) {

                $entityManager->remove($productOption);
                $entityManager->flush();

                return $this->redirectToRoute($routes['product_options'], ['id' => $restaurantId]);
            }

            foreach ($productOption->getValues() as $optionValue) {
                if (null === $optionValue->getCode()) {
                    $optionValue->setCode(Uuid::uuid4()->toString());
                }
            }

            $entityManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ], $routes));
    }

    public function restaurantProductOptionPreviewAction(Request $request,
        FactoryInterface $productOptionFactory,
        ObjectNormalizer $normalizer,
        LocaleProviderInterface $localeProvider)
    {
        $productOption = $productOptionFactory
            ->createNew();

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            $enabledValues = $productOption->getValues()->filter(function ($value) {
                return $value->isEnabled();
            });

            $productOption->getValues()->clear();
            foreach ($enabledValues as $optionValue) {
                $productOption->getValues()->add($optionValue);
            }

            foreach ($productOption->getValues() as $optionValue) {
                // FIXME We shouldn't need to call setCurrentLocale
                $optionValue->setCurrentLocale($localeProvider->getDefaultLocaleCode());
                if (null === $optionValue->getCode()) {
                    $optionValue->setCode(Uuid::uuid4()->toString());
                }
            }


            return new JsonResponse(
                $normalizer->normalize($productOption, context: [
                    'groups' => ['product_option'],
                    // Disable IRI generation as objects don't have ids
                    'iri' => false,
                ])
            );
        }

        throw new BadRequestHttpException();
    }

    public function newRestaurantProductOptionAction($id, Request $request,
        FactoryInterface $productOptionFactory,
        EntityManagerInterface $entityManager)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $productOption = $productOptionFactory
            ->createNew();

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            $productOption->setCode(Uuid::uuid4()->toString());
            foreach ($productOption->getValues() as $optionValue) {
                $optionValue->setCode(Uuid::uuid4()->toString());
            }

            $restaurant->addProductOption($productOption);

            $entityManager->flush();

            return $this->redirectToRoute($routes['product_options'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ], $routes));
    }

    public function stripeOAuthRedirectAction($id, Request $request,
        SettingsManager $settingsManager,
        JWTEncoderInterface $jwtEncoder)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $redirectUri = $this->generateUrl(
            'stripe_connect_standard_account',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $user = $this->getUser();

        $livemode = $request->query->getBoolean('livemode', false);

        // @see https://stripe.com/docs/connect/oauth-reference
        $prefillingData = [
            'stripe_user[email]' => $user->getEmail(),
            'stripe_user[url]' => $restaurant->getWebsite(),
            // TODO : set this after https://github.com/coopcycle/coopcycle-web/issues/234 is solved
            // 'stripe_user[country]' => $restaurant->getAddress()->getCountry(),
            'stripe_user[phone_number]' => $restaurant->getTelephone(),
            'stripe_user[business_name]' => $restaurant->getLegalName(),
            'stripe_user[business_type]' => 'Restaurant',
            'stripe_user[first_name]' => $user->getGivenName(),
            'stripe_user[last_name]' => $user->getFamilyName(),
            'stripe_user[street_address]' => $restaurant->getAddress()->getStreetAddress(),
            'stripe_user[city]' => $restaurant->getAddress()->getAddressLocality(),
            'stripe_user[zip]' => $restaurant->getAddress()->getPostalCode(),
            'stripe_user[physical_product]' => 'Food',
            'stripe_user[shipping_days]' => 1,
            'stripe_user[product_category]' => 'Food',
            'stripe_user[product_description]' => 'Food',
            'stripe_user[currency]' => $settingsManager->get('currency_code'),
        ];

        // @see https://stripe.com/docs/connect/standard-accounts#integrating-oauth

        $key = $livemode ? 'stripe_live_connect_client_id' : 'stripe_test_connect_client_id';
        $clientId = $settingsManager->get($key);

        $redirectAfterUri = $this->generateUrl(
            $request->attributes->get('redirect_after'),
            ['id' => $restaurant->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Use a JWT as the "state" parameter
        // @see https://stripe.com/docs/connect/oauth-reference#get-authorize-request
        $state = $jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            // The "iss" (Issuer) claim contains a redirect URL
            'iss' => $redirectAfterUri,
            // The custom "slm" (Stripe livemode) contains a boolean
            'slm' => $livemode ? 'yes' : 'no',
        ]);

        $queryString = http_build_query(array_merge(
            $prefillingData,
            [
                'response_type' => 'code',
                'client_id' => $clientId,
                'scope' => 'read_write',
                'redirect_uri' => $redirectUri,
                'state' => $state,
            ]
        ));

        return $this->redirect('https://connect.stripe.com/oauth/authorize?' . $queryString);
    }

    /**
     * @see https://www.mercadopago.com.mx/developers/es/guides/marketplace/api/create-marketplace/
     */
    public function mercadopagoOAuthRedirectAction($id, Request $request,
        SettingsManager $settingsManager,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        MercadopagoManager $mercadopagoManager)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $redirectUri = $this->generateUrl(
            'mercadopago_oauth_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $redirectAfterUri = $this->generateUrl(
            $request->attributes->get('redirect_after'),
            ['id' => $restaurant->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Use a JWT as the "state" parameter
        // @see https://stripe.com/docs/connect/oauth-reference#get-authorize-request
        $state = $jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            // The "iss" (Issuer) claim contains a redirect URL
            'iss' => $redirectAfterUri,
            // The "sub" (Subject) claim contains a restaurant IRI
            'sub' => $iriConverter->getIriFromResource($restaurant),
            // The custom "mplm" (Mercado Pago livemode) contains a boolean
            'mplm' => 'no',
        ]);

        $mercadopagoManager->configure();

        $oAuth = new MercadoPago\Client\OAuth\OAuthClient();

        $url = $oAuth->getAuthorizationURL($settingsManager->get('mercadopago_app_id'), $redirectUri, $state);

        // Temporary fix until this is merged
        // https://github.com/mercadopago/sdk-php/pull/539
        if (!str_starts_with($url, 'https://auth.mercadopago.com/authorization')) {
            $url = str_replace('https://auth.mercadopago.com', 'https://auth.mercadopago.com/authorization', $url);
        }

        return $this->redirect($url);
    }

    public function mercadopagoOAuthRemoveAction(
        $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $restaurant->setMercadopagoAccount(null);

        $entityManager->flush();

        $this->addFlash(
            'notice',
            $translator->trans('form.local_business.mercadopago.remove.connection.success')
        );

        return $this->redirectToRoute(
            $request->attributes->get('redirect_after'),
            ['id' => $id]
        );
    }

    public function preparationTimeAction($id, Request $request, PreparationTimeCalculator $calculator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $routes = $request->attributes->get('routes');

        $hasRules = count($restaurant->getPreparationTimeRules()) > 0;
        if (!$hasRules) {
            $config = $calculator->getDefaultConfig();
            foreach ($config as $expression => $time) {
                $preparationTimeRule = new PreparationTimeRule();
                $preparationTimeRule->setExpression($expression);
                $preparationTimeRule->setTime($time);

                $restaurant->addPreparationTimeRule($preparationTimeRule);
            }
        }

        $originalPreparationTimeRules = new ArrayCollection();
        foreach ($restaurant->getPreparationTimeRules() as $preparationTimeRule) {
            $originalPreparationTimeRules->add($preparationTimeRule);
        }

        $form = $this->createForm(PreparationTimeRulesType::class, $restaurant);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $restaurant = $form->getData();

            foreach ($originalPreparationTimeRules as $preparationTimeRule) {
                if (false === $restaurant->getPreparationTimeRules()->contains($preparationTimeRule)) {

                    $restaurant->getPreparationTimeRules()
                        ->removeElement($preparationTimeRule);

                    $this->entityManager->remove($preparationTimeRule);
                }
            }

            $this->entityManager->flush();

            return $this->redirectToRoute($routes['success'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'show_defaults_warning' => !$hasRules,
            'form' => $form->createView(),
        ]));
    }

    public function statsAction($id, Request $request,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator,
        TaxesHelper $taxesHelper,
        CubeJsTokenFactory $tokenFactory)
    {
        $tab = $request->query->get('tab', 'orders');

        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $request->attributes->get('routes');

        [ $start, $end ] = $this->extractRange($request);

        $refundedOrders = $entityManager->getRepository(Order::class)
            ->findRefundedOrdersByRestaurantAndDateRange(
                $restaurant,
                $start,
                $end
            );

        $showOnlyMealVouchers = $request->query->has('show_only') && 'meal_vouchers' === $request->query->get('show_only');

        $stats = new RestaurantStats(
            $entityManager,
            $start,
            $end,
            $restaurant,
            $paginator,
            $this->getParameter('kernel.default_locale'),
            $translator,
            $taxesHelper,
            withVendorName: false,
            withMessenger: false,
            nonProfitsEnabled: $this->getParameter('nonprofits_enabled'),
            showOnlyMealVouchers: $showOnlyMealVouchers
        );

        if ($request->isMethod('POST')) {

            $filename = sprintf('%s-%s-%s.csv',
                $slugify->slugify($restaurant->getName()),
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            );

            $response = new Response($stats->toCsv());
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ));

            return $response;
        }

        return $this->render('restaurant/stats.html.twig', $this->auth($this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'stats' => $stats,
            'refunded_orders' => $refundedOrders, // new ArrayCollection($refundedOrders),
            'start' => $start,
            'end' => $end,
            'tab' => $tab,
            'cube_token' => $tokenFactory->createToken(['vendor_id' => $restaurant->getId()]),
            'picker_type' => $request->query->has('date') ? 'date' : 'month',
            'with_details' => $request->query->getBoolean('details', false),
            'show_only_meal_vouchers' => $showOnlyMealVouchers
        ])));
    }

    private function extractRange(Request $request)
    {
        $date = new \DateTime($request->query->get('date'));

        $type = $request->query->has('date') ? 'date' : 'month';

        if ($request->query->has('month')) {
            $month = $request->query->get('month');
            if (1 === preg_match('/([0-9]{4})-([0-9]{2})/', $month, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                $date->setDate($year, $month, 1);
            }
        }

        $start = clone $date;
        $end = clone $date;

        $start->setTime(0, 0, 1);
        $end->setTime(23, 59, 59);

        if ($type === 'month') {
            $start->setDate($date->format('Y'), $date->format('m'), 1);
            // Last day of month
            $end->setDate($date->format('Y'), $date->format('m'), $date->format('t'));
        }

        return [
            $start,
            $end
        ];
    }

    public function newRestaurantReusablePackagingAction($id, Request $request, TranslatorInterface $translator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $form = $this->createForm(ReusablePackagingType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $reusablePackaging = $form->getData();

            $restaurant->addReusablePackaging($reusablePackaging);

            $this->entityManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute($this->getRestaurantRoute('deposit_refund'), ['id' => $id]);
        }

        return $this->render('restaurant/reusable_packaging.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ]));
    }

    public function restaurantDepositRefundAction($id, Request $request, TranslatorInterface $translator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $form = $this->createForm(DepositRefundSettingsType::class, $restaurant);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->entityManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            $routes = $this->getRestaurantRoutes();

            return $this->redirectToRoute($routes['deposit_refund'], ['id' => $id]);
        }

        return $this->render('restaurant/deposit_refund.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ]));
    }

    public function restaurantPromotionsAction($id, Request $request)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        return $this->render('restaurant/promotions.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'promotions' => $restaurant->getPromotions(),
        ]));
    }

    public function newRestaurantPromotionAction($id, Request $request, TranslatorInterface $translator)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $this->accessControl($restaurant);

        $routes = $this->getRestaurantRoutes();

        if ($request->query->has('type')) {

            $type = $request->query->get('type');

            switch ($type) {
                case 'offer_delivery':

                    $form = $this->createForm(OfferDeliveryType::class, null, [
                        'local_business' => $restaurant
                    ]);

                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {

                        $promotion = $form->getData();

                        $restaurant->addPromotion($promotion);

                        $this->entityManager->flush();

                        // $this->addFlash(
                        //     'notice',
                        //     $translator->trans('global.changesSaved')
                        // );

                        return $this->redirectToRoute($routes['promotions'], ['id' => $id]);
                    }

                    return $this->render('restaurant/promotion.html.twig', $this->withRoutes([
                        'layout' => $request->attributes->get('layout'),
                        'restaurant' => $restaurant,
                        'form' => $form->createView(),
                        'promotion_type' => $type,
                    ]));

                case 'items_total':
                    $form = $this->createForm(ItemsTotalBasedPromotionType::class, null, [
                        'local_business' => $restaurant
                    ]);

                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {

                        $promotion = $form->getData();

                        $restaurant->addPromotion($promotion);

                        $this->entityManager->flush();

                        // $this->addFlash(
                        //     'notice',
                        //     $translator->trans('global.changesSaved')
                        // );

                        return $this->redirectToRoute($routes['promotions'], ['id' => $id]);
                    }

                    return $this->render('restaurant/promotion.html.twig', $this->withRoutes([
                        'layout' => $request->attributes->get('layout'),
                        'restaurant' => $restaurant,
                        'form' => $form->createView(),
                        'promotion_type' => $type,
                    ]));
            }
        }

        return $this->redirectToRoute($routes['promotions'], ['id' => $id]);
    }

    public function restaurantPromotionAction($restaurantId, $promotionId, Request $request)
    {
        // TODO Implement

        throw $this->createNotFoundException();
    }

    public function deleteProductImageAction($restaurantId, $productId, $imageName,
        Request $request,
        ObjectRepository $productRepository,
        UploadHandler $uploadHandler)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $this->accessControl($restaurant);

        $product = $productRepository
            ->find($productId);

        if (!$product) {
            throw $this->createNotFoundException();
        }

        $image = $this->entityManager
            ->getRepository(ProductImage::class)
            ->findOneByImageName($imageName);

        if (!$image) {
            throw $this->createNotFoundException();
        }

        if (!$product->getImages()->contains($image)) {
            throw new BadRequestHttpException(sprintf('Product "%s" does not belong to product #%d', $imageName, $productId));
        }

        $uploadHandler->remove($image, 'imageFile');

        $product->getImages()->removeElement($image);
        $this->entityManager->flush();

        return new Response('', 204);
    }

    public function edenredTransactionsAction(
        SlugifyInterface $slugify,
        Request $request)
    {
        return $this->redirectToRoute('admin_restaurants_meal_voucher_transactions', $request->query->all());
    }

    public function addRestaurantsEdenredAction(Request $request, SynchronizerClient $synchronizerClient)
    {
        $form = $this->createFormBuilder()
            ->add('restaurants', CollectionType::class, [
                'entry_type' => EntityType::class,
                'entry_options' => [
                    'label' => false,
                    'class' => LocalBusiness::class,
                    'choice_label' => 'name',
                ],
                'label' => 'restaurants.edenred.add_list_title',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])
            ->getForm();

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $restaurantsToSync = [];
            $errors = [];

            foreach($form->get('restaurants')->getData() as $restaurant) {
                if ($restaurant->hasAdditionalProperty('siret') && !empty($restaurant->getAdditionalPropertyValue('siret'))) {
                    if (!$restaurant->getEdenredSyncSent()) {
                        $restaurantsToSync[] = $restaurant;
                    }
                } else {
                    $errors[] = $this->translator->trans('restaurants.edenred.sending_failed.no_siret', [
                        '%restaurant_name%' => $restaurant->getName()
                    ]);
                }
            }

            $response = $synchronizerClient->synchronizeMerchants($restaurantsToSync);

            if ($response->getStatusCode() !== 200) {
                $responseData = json_decode((string) $response->getContent(false), true);
                $errors[] = $responseData["detail"];
            } else {
                foreach($restaurantsToSync as $restaurant) {
                    $restaurant->setEdenredSyncSent(true);
                }
                $this->entityManager->flush();
            }

            return $this->render('restaurant/edenred_sync.html.twig', $this->withRoutes([
                'layout' => $request->attributes->get('layout'),
                'form' => $form->createView(),
                'sending_result' => $response->getStatusCode() === 200,
                'errors' => $errors,
            ]));
        }

        if ($request->query->has('section') && $request->query->get('section') === 'added') {
            $restaurants = $this->entityManager
                ->getRepository(LocalBusiness::class)
                ->findBy([ 'edenredSyncSent' => true ]);

            return $this->render('restaurant/edenred_sync.html.twig', $this->withRoutes([
                'layout' => $request->attributes->get('layout'),
                'restaurants' => $restaurants,
            ]));
        }

        return $this->render('restaurant/edenred_sync.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'form' => $form->createView(),
        ]));
    }

    public function refreshRestaurantEdenredAction($restaurantId, Request $request, SynchronizerClient $synchronizerClient)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);

        $response = $synchronizerClient->getMerchant($restaurant);

        $responseData = json_decode((string) $response->getContent(false), true);

        $hasUpdates = false;

        if ($response->getStatusCode() === 200) {
            if (isset($responseData['merchantId']) && !empty($responseData['merchantId']) && null === $restaurant->getEdenredMerchantId()) {
                $restaurant->setEdenredMerchantId($responseData['merchantId']);
                $hasUpdates = true;
            }
            if (isset($responseData['acceptsTRCard']) && !empty($responseData['acceptsTRCard']) && false === $restaurant->isEdenredTRCardEnabled()) {
                $restaurant->setEdenredTRCardEnabled($responseData['acceptsTRCard']);
                $hasUpdates = true;
            }

            if ($hasUpdates) {
                $this->entityManager->flush();
            }
        }

        $this->addFlash(
            'notice',
            $this->translator->trans(
                $hasUpdates ? 'restaurants.edenred.refresh.has_updates' : 'restaurants.edenred.refresh.no_updates', [
                    '%restaurant_name%' => $restaurant->getName(),
                ])
        );

        return $this->redirectToRoute('admin_add_restaurants_edenred', [ 'section' => 'added' ]);
    }

    public function restaurantApiAction(
        $id,
        Request $request)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $qb = $this->entityManager
            ->getRepository(ApiApp::class)
            ->createQueryBuilder('a')
            ->andWhere('a.shop = :shop')
            ->setParameter('shop', $restaurant);

        $apiApps = $qb->getQuery()->getResult();

        $routes = $request->attributes->get('routes');

        return $this->render('restaurant/api.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'api_apps' => $apiApps,
        ], $routes));
    }

    public function newRestaurantApiAction(
        $id,
        Request $request)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $apiApp = new ApiApp();
        $apiApp->setShop($restaurant);

        $form = $this->createForm(ApiAppType::class, $apiApp, [
            'with_stores' => false
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $apiApp = $form->getData();

            $this->entityManager->persist($apiApp);
            $this->entityManager->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('api_apps.created.message')
            );

            return $this->redirectToRoute('admin_restaurant_api', [ 'id' => $id ]);
        }

        $routes = $request->attributes->get('routes');

        return $this->render('restaurant/api_form.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ], $routes));
    }

    public function restaurantImageFromUrlAction($id, Request $request,
        UploadHandler $uploadHandler,
        EntityManagerInterface $entityManager)
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($id);

        $url = $request->request->get('url');

        // https://stackoverflow.com/questions/40454950/set-symfony-uploaded-file-by-url-input

        $file = tmpfile();
        $newfile = stream_get_meta_data($file)['uri'];

        copy($url, $newfile);
        $mimeType = mime_content_type($newfile);
        $size = filesize($newfile);
        $finalName = md5(uniqid(rand(), true)) . '.jpg';

        $uploadedFile = new UploadedFile($newfile, $finalName, $mimeType, $size);

        $restaurant->setBannerImageFile($uploadedFile);

        $uploadHandler->upload($restaurant, 'bannerImageFile');

        unlink($newfile);

        $restaurant->setBannerImageName(
            $restaurant->getBannerImageFile()->getBasename()
        );

        $entityManager->flush();

        return new JsonResponse(
            ['imageName' => $restaurant->getBannerImageName()]
        );
    }

    public function mealVouchersTransactionsAction(
        SlugifyInterface $slugify,
        Request $request)
    {
        $qb = $this->entityManager->getRepository(OrderInterface::class)
            ->createQueryBuilder('o');

        $qb->join(PaymentInterface::class, 'p', Expr\Join::WITH, 'p.order = o.id');
        $qb->join(PaymentMethodInterface::class, 'pm', Expr\Join::WITH, 'p.method = pm.id');

        $paymentMethods = ['EDENRED', 'CONECS', 'SWILE', 'RESTOFLASH'];

        $qb->andWhere('pm.code IN (:code)');
        $qb->andWhere('o.state = :order_state');
        $qb->andWhere('p.state = :payment_state');

        $qb->setParameter('code', $paymentMethods);
        $qb->setParameter('order_state', OrderInterface::STATE_FULFILLED);
        $qb->setParameter('payment_state', PaymentInterface::STATE_COMPLETED);

        $month = new \DateTime('now');
        if ($request->query->has('month')) {
            $month = new \DateTime($request->query->get('month'));
        }

        $start = new \DateTime(
            sprintf('first day of %s', $month->format('F Y'))
        );
        $end = new \DateTime(
            sprintf('last day of %s', $month->format('F Y'))
        );

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        $qb = OrderRepository::addShippingTimeRangeClause($qb, 'o', $start, $end);

        $qb->orderBy('o.shippingTimeRange', 'DESC');

        $hash = new \SplObjectStorage();

        $orders = $qb->getQuery()->getResult();

        foreach ($orders as $order) {

            $restaurant = $order->getRestaurant();

            if (!$hash->contains($restaurant)) {
                $hash->attach($restaurant, []);
            }

            $hash->attach($restaurant, array_merge($hash[$restaurant], [ $order ]));
        }

        if ($request->isMethod('POST') && $request->request->has('restaurant')) {

            $restaurantId = $request->request->getInt('restaurant');

            $exported = $this->entityManager->getRepository(LocalBusiness::class)
                ->find($restaurantId);

            if (null === $exported) {

                throw $this->createNotFoundException();
            }

            $filename = sprintf('edenred-%s-%s.csv',
                $month->format('Y-m'),
                $slugify->slugify($exported->getName())
            );

            $csv = CsvWriter::createFromString('');

            $numberFormatter = \NumberFormatter::create($request->getLocale(), \NumberFormatter::DECIMAL);
            $numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
            $numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

            $heading = [
                'Order number',
                'Completed at',
                'Total amount',
                'Voucher amount',
                'Platform fee',
                'Payment method',
            ];

            $records = [];
            foreach ($hash[$exported] as $order) {

                $voucherPayment = $order->getLastPaymentByMethod(['EDENRED', 'CONECS', 'SWILE', 'RESTOFLASH'], PaymentInterface::STATE_COMPLETED);

                $records[] = [
                    $order->getNumber(),
                    $order->getShippingTimeRange()->getLower()->format('Y-m-d'),
                    $numberFormatter->format($order->getTotal() / 100),
                    $numberFormatter->format($voucherPayment->getAmount() / 100),
                    $numberFormatter->format($order->getFeeTotal() / 100),
                    mb_ucfirst(mb_strtolower($voucherPayment->getMethod()->getCode())),
                ];
            }

            $csv->insertOne($heading);
            $csv->insertAll($records);

            $response = new Response($csv->getContent());
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ));

            return $response;
        }

        return $this->render('restaurant/meal_vouchers_transactions.html.twig', $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'month' => $month,
            'orders' => $hash,
            'payment_methods' => $paymentMethods,
        ]));
    }
}
