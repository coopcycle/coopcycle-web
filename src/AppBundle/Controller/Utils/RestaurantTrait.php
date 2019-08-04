<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Restaurant\PreparationTimeRule;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Zone;
use AppBundle\Form\ClosingRuleType;
use AppBundle\Form\MenuEditorType;
use AppBundle\Form\MenuTaxonType;
use AppBundle\Form\MenuType;
use AppBundle\Form\PreparationTimeRulesType;
use AppBundle\Form\ProductOptionType;
use AppBundle\Form\ProductType;
use AppBundle\Form\RestaurantType;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Utils\MenuEditor;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\RestaurantStats;
use AppBundle\Utils\ValidationUtils;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

trait RestaurantTrait
{
    abstract protected function getRestaurantList(Request $request);

    abstract protected function getRestaurantRoutes();

    /**
     * @HideSoftDeleted
     */
    public function restaurantListAction(Request $request)
    {
        $routes = $request->attributes->get('routes');

        [ $restaurants, $pages, $page ] = $this->getRestaurantList($request);

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'restaurants' => $restaurants,
            'pages' => $pages,
            'page' => $page,
            'dashboard_route' => $routes['dashboard'],
            'menu_taxon_route' => $routes['menu_taxon'],
            'menu_taxons_route' => $routes['menu_taxons'],
            'restaurant_route' => $routes['restaurant'],
            'products_route' => $routes['products']
        ]);
    }

    protected function withRoutes($params, $routes)
    {
        $routes = array_merge($routes, $this->getRestaurantRoutes());

        $routeParams = [];
        foreach ($routes as $key => $value) {
            $routeParams[sprintf('%s_route', $key)] = $value;
        }

        return array_merge($params, $routeParams);
    }

    protected function renderRestaurantForm(Restaurant $restaurant, Request $request)
    {
        $form = $this->createForm(RestaurantType::class, $restaurant, [
            'additional_properties' => $this->getLocalizedLocalBusinessProperties(),
        ]);

        // Associate Stripe account with restaurant
        if ($request->getSession()->getFlashBag()->has('stripe_account')) {
            $messages = $request->getSession()->getFlashBag()->get('stripe_account');
            if (!empty($messages)) {
                foreach ($messages as $stripeAccountId) {
                    $stripeAccount = $this->getDoctrine()
                        ->getRepository(StripeAccount::class)
                        ->find($stripeAccountId);
                    if ($stripeAccount) {
                        $restaurant->addStripeAccount($stripeAccount);
                        $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

                        $this->addFlash(
                            'notice',
                            $this->get('translator')->trans('form.local_business.stripe_account.success')
                        );
                    }
                }
            }
        }

        $activationErrors = [];
        $formErrors = [];
        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            if ($form->isValid()) {
                $restaurant = $form->getData();

                if ($form->getClickedButton() && 'delete' === $form->getClickedButton()->getName()) {

                    $this->getDoctrine()->getManagerForClass(Restaurant::class)->remove($restaurant);
                    $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

                    return $this->redirectToRoute($routes['restaurants']);
                }

                if ($restaurant->getId() === null && !$this->getUser()->hasRole('ROLE_ADMIN')) {
                    $this->getUser()->addRestaurant($restaurant);
                }

                // Make sure the restaurant can be enabled, or disable it
                $violations = $this->get('validator')->validate($restaurant, null, ['activable']);
                if (count($violations) > 0) {
                    $restaurant->setEnabled(false);
                }

                $this->getDoctrine()->getManagerForClass(Restaurant::class)->persist($restaurant);
                $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

                $this->addFlash(
                    'notice',
                    $this->get('translator')->trans('global.changesSaved')
                );

                return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
            } else {
                $violations = new ConstraintViolationList();
                foreach ($form->getErrors(true) as $error) {
                    $violations->add($error->getCause());
                }
                $formErrors = ValidationUtils::serializeValidationErrors($violations);
            }

        } else {
            $validator = $this->get('validator');
            $violations = $validator->validate($restaurant, null, ['activable']);
            $activationErrors = ValidationUtils::serializeValidationErrors($violations);
        }

        $zones = $this->getDoctrine()->getRepository(Zone::class)->findAll();
        $zoneNames = [];
        foreach ($zones as $zone) {
            array_push($zoneNames, $zone->getName());
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'zoneNames' => json_encode($zoneNames),
            'restaurant' => $restaurant,
            'activationErrors' => $activationErrors,
            'formErrors' => $formErrors,
            'form' => $form->createView(),
            'layout' => $request->attributes->get('layout'),
            'deliveryPerimeterExpression' => json_encode($restaurant->getDeliveryPerimeterExpression())
        ], $routes));
    }

    public function restaurantAction($id, Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Restaurant::class);

        $restaurant = $repository->find($id);

        $this->accessControl($restaurant);

        return $this->renderRestaurantForm($restaurant, $request);
    }

    public function newRestaurantAction(Request $request)
    {
        // TODO Check roles
        $restaurant = new Restaurant();

        return $this->renderRestaurantForm($restaurant, $request);
    }

    protected function renderRestaurantDashboard(
        Request $request,
        JWTManagerInterface $jwtManager,
        Restaurant $restaurant)
    {
        $this->accessControl($restaurant);

        $date = new \DateTime('now');
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }

        $qb = $this->get('sylius.repository.order')
            ->createQueryBuilder('o')
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere('DATE(o.shippedAt) >= :date')
            ->andWhere('o.state != :state')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('date', $date)
            ->setParameter('state', OrderInterface::STATE_CART);
            ;

        $orders = $qb->getQuery()->getResult();

        $routes = $request->attributes->get('routes');

        $order = null;
        if ($request->query->has('order')) {
            $orderId = $request->query->getInt('order');
            $order = $this->get('sylius.repository.order')->find($orderId);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'restaurant_normalized' => $this->get('serializer')->normalize($restaurant, 'jsonld', [
                'resource_class' => Restaurant::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['restaurant']
            ]),
            'orders_normalized' => $this->get('serializer')->normalize($orders, 'jsonld', [
                'resource_class' => Order::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['order', 'place']
            ]),
            'order_normalized' => $order ? $this->get('serializer')->normalize($order, 'jsonld', [
                'resource_class' => Order::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['order', 'place']
            ]) : null,
            'routes' => $routes,
            'date' => $date,
            'jwt' => $jwtManager->create($this->getUser()),
        ], $routes));
    }

    public function restaurantDashboardAction($restaurantId, Request $request, JWTManagerInterface $jwtManager)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        return $this->renderRestaurantDashboard($request, $jwtManager, $restaurant);
    }

    public function restaurantMenuTaxonsAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $routes = $request->attributes->get('routes');
        $menus = $restaurant->getTaxons();

        $forms = [];
        foreach ($menus as $menu) {
            $forms[$menu->getId()] = $this->createForm(MenuTaxonType::class, $menu)->createView();
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'menus' => $menus,
            'restaurant' => $restaurant,
            'forms' => $forms,
        ], $routes));
    }

    public function activateRestaurantMenuTaxonAction($restaurantId, $menuId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        $menuTaxon = $this->get('sylius.repository.taxon')
            ->find($menuId);

        $restaurant->setMenuTaxon($menuTaxon);

        $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

        $this->addFlash(
            'notice',
            $this->get('translator')->trans('restaurant.menus.activated', ['%menu_name%' => $menuTaxon->getName()])
        );

        $routes = $request->attributes->get('routes');

        return $this->redirectToRoute($routes['menu_taxons'], [
            'id' => $restaurant->getId(),
        ]);
    }


    public function deleteRestaurantMenuTaxonChildAction($restaurantId, $menuId, $sectionId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        $menuTaxon = $this->get('sylius.repository.taxon')->find($menuId);
        $toRemove = $this->get('sylius.repository.taxon')->find($sectionId);

        $menuTaxon->removeChild($toRemove);

        $this->get('sylius.manager.taxon')->flush();

        $routes = $request->attributes->get('routes');

        return $this->redirectToRoute($routes['menu_taxon'], [
            'restaurantId' => $restaurant->getId(),
            'menuId' => $menuTaxon->getId()
        ]);
    }

    public function newRestaurantMenuTaxonAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $routes = $request->attributes->get('routes');

        $menuTaxon = $this->get('sylius.factory.taxon')->createNew();

        $uuid = Uuid::uuid1()->toString();

        $menuTaxon->setCode($uuid);
        $menuTaxon->setSlug($uuid);

        $form = $this->createForm(MenuTaxonType::class, $menuTaxon);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $menuTaxon = $form->getData();

            $this->get('sylius.repository.taxon')->add($menuTaxon);

            $restaurant->addTaxon($menuTaxon);
            $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

            return $this->redirectToRoute($routes['menu_taxon'], [
                'restaurantId' => $restaurant->getId(),
                'menuId' => $menuTaxon->getId()
            ]);
        }

        $menuEditor = new MenuEditor($restaurant, $menuTaxon);
        $menuEditorForm = $this->createForm(MenuEditorType::class, $menuEditor);

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'menu_editor_form' => $menuEditorForm->createView(),
        ], $routes));
    }

    /**
     * @HideSoftDeleted
     */
    public function restaurantMenuTaxonAction($restaurantId, $menuId, Request $request)
    {
        $routes = $request->attributes->get('routes');

        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        $menuTaxon = $this->get('sylius.repository.taxon')
            ->find($menuId);

        $form = $this->createForm(MenuTaxonType::class, $menuTaxon);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $menuTaxon = $form->getData();

            if ($form->getClickedButton()) {

                if ('addChild' === $form->getClickedButton()->getName()) {
                    $childName = $form->get('childName')->getData();

                    $uuid = Uuid::uuid1()->toString();

                    $childTaxon = $this->get('sylius.factory.taxon')->createNew();
                    $childTaxon->setCode($uuid);
                    $childTaxon->setSlug($uuid);
                    $childTaxon->setName($childName);

                    $menuTaxon->addChild($childTaxon);
                    $this->get('sylius.manager.taxon')->flush();

                    $this->addFlash(
                        'notice',
                        $this->get('translator')->trans('global.changesSaved')
                    );
                }

                if ('delete' === $form->getClickedButton()->getName()) {
                    $restaurant->removeTaxon($menuTaxon);
                    $this->get('sylius.manager.taxon')->remove($menuTaxon);
                    $this->get('sylius.manager.taxon')->flush();
                }

                return $this->redirect($request->headers->get('referer'));
            }

            $this->get('sylius.manager.taxon')->flush();

            return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
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

                $newSectionPositions[$child->getPosition()] = $child->getId();

                foreach ($child->getTaxonProducts() as $taxonProduct) {

                    $taxonProduct->setTaxon($child);

                    foreach ($originalTaxonProducts[$child] as $originalTaxonProduct) {
                        if (!$child->getTaxonProducts()->contains($originalTaxonProduct)) {
                            $child->getTaxonProducts()->removeElement($originalTaxonProduct);
                            $this->getDoctrine()->getManagerForClass(ProductTaxon::class)->remove($originalTaxonProduct);
                        }
                    }
                }
            }

            ksort($newSectionPositions);
            $newSectionPositions = array_values($newSectionPositions);

            if ($originalSectionPositions !== $newSectionPositions) {
                $this->get('sylius.repository.taxon')->reorder($menuTaxon, 'position');
            }

            $this->get('sylius.manager.taxon')->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'menu_editor_form' => $menuEditorForm->createView(),
        ], $routes));
    }

    public function restaurantPlanningAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $form = $this->createForm(ClosingRuleType::class);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $form->handleRequest($request);

        $routes = $request->attributes->get('routes');

        if ($form->isSubmitted() && $form->isValid()) {
            $closingRule = $form->getData();
            $closingRule->setRestaurant($restaurant);
            $manager = $this->getDoctrine()->getManagerForClass(ClosingRule::class);
            $manager->persist($closingRule);
            $manager->flush();
            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );
            return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'closing_rules_json' => $this->get('serializer')->serialize($restaurant->getClosingRules(), 'json', ['groups' => ['planning']]),
            'opening_hours_json' => json_encode($restaurant->getOpeningHours()),
            'restaurant' => $restaurant,
            'routes' => $routes,
            'form' => $form->createView()
        ], $routes));
    }

    /**
     * @HideSoftDeleted
     */
    public function restaurantProductsAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $routes = $request->attributes->get('routes');

        // TODO Use Criteria API for ordering
        $products = $restaurant->getProducts()->toArray();
        usort($products, function ($a, $b) {
            return $a->getName() < $b->getName() ? -1 : 1;
        });

        $forms = [];
        foreach ($products as $product) {
            $forms[$product->getId()] = $this->createForm(ProductType::class, $product)->createView();
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'products' => $products,
            'restaurant' => $restaurant,
            'forms' => $forms,
        ], $routes));
    }

    public function restaurantProductAction($restaurantId, $productId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        $product = $this->get('sylius.repository.product')
            ->find($productId);

        // FIXME
        // Configure mapping to avoid having to call this
        $product->setRestaurant($restaurant);

        $form = $this->createForm(ProductType::class, $product);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $product = $form->getData();

            if ($form->getClickedButton()) {
                if ('delete' === $form->getClickedButton()->getName()) {
                    $this->get('sylius.manager.product')->remove($product);
                }
            }

            $this->get('sylius.manager.product')->flush();

            return $this->redirectToRoute($routes['products'], ['id' => $restaurantId]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'product' => $product,
            'form' => $form->createView()
        ], $routes));
    }

    public function newRestaurantProductAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $product = $this->get('sylius.factory.product')
            ->createNew();

        $product->setEnabled(false);

        // FIXME
        // Configure mapping to avoid having to call this
        $product->setRestaurant($restaurant);

        $form = $this->createForm(ProductType::class, $product);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $product = $form->getData();

            $this->get('sylius.repository.product')->add($product);

            return $this->redirectToRoute($routes['products'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'product' => $product,
            'form' => $form->createView()
        ], $routes));
    }

    /**
     * @HideSoftDeleted
     */
    public function restaurantProductOptionsAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'options' => $restaurant->getProductOptions(),
            'restaurant' => $restaurant,
        ], $routes));
    }

    public function restaurantProductOptionAction($restaurantId, $optionId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        $productOption = $this->get('sylius.repository.product_option')
            ->find($optionId);

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            if ($form->getClickedButton() && 'delete' === $form->getClickedButton()->getName()) {

                $this->get('sylius.manager.product')->remove($productOption);
                $this->get('sylius.manager.product_option')->flush();

                return $this->redirectToRoute($routes['product_options'], ['id' => $restaurantId]);
            }

            foreach ($productOption->getValues() as $optionValue) {
                if (null === $optionValue->getCode()) {
                    $optionValue->setCode(Uuid::uuid4()->toString());
                }
            }

            $this->get('sylius.manager.product_option')->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ], $routes));
    }

    public function newRestaurantProductOptionAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $productOption = $this->get('sylius.factory.product_option')
            ->createNew();

        $productOption->setRestaurant($restaurant);

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            $productOption->setCode(Uuid::uuid4()->toString());
            foreach ($productOption->getValues() as $optionValue) {
                $optionValue->setCode(Uuid::uuid4()->toString());
            }

            $this->get('sylius.manager.product_option')->flush();

            return $this->redirectToRoute($routes['product_options'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
        ], $routes));
    }

    public function stripeOAuthRedirectAction($id, Request $request, SettingsManager $settingsManager)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
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
            'stripe_user[currency]' => 'EUR' // TODO Get configured currency
        ];

        // @see https://stripe.com/docs/connect/standard-accounts#integrating-oauth

        $key = $livemode ? 'stripe_live_connect_client_id' : 'stripe_test_connect_client_id';
        $clientId = $settingsManager->get($key);

        // Store livemode in FlashBag for later
        $request->getSession()->getFlashBag()->set('stripe_connect_livemode', [ $livemode ? 'yes' : 'no' ]);

        // Encode the current URL as base64
        // FIXME Use encryption instead of base64
        $redirectAfterUri = $this->generateUrl(
            $request->attributes->get('redirect_after'),
            ['id' => $restaurant->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $state = base64_encode($redirectAfterUri);

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

    public function preparationTimeAction($id, Request $request, PreparationTimeCalculator $calculator)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

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

                    $this->getDoctrine()
                        ->getManagerForClass(PreparationTimeRule::class)
                        ->remove($preparationTimeRule);
                }
            }

            $em = $this->getDoctrine()->getManagerForClass(Restaurant::class);
            $em->flush();

            return $this->redirectToRoute($routes['success'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'show_defaults_warning' => !$hasRules,
            'form' => $form->createView(),
        ], []));
    }

    public function statsAction($id, Request $request, SlugifyInterface $slugify)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $this->accessControl($restaurant);

        $routes = $request->attributes->get('routes');

        $date = new \DateTime();

        if ($request->query->has('month')) {
            $month = $request->query->get('month');
            preg_match('/([0-9]{4})-([0-9]{2})/', $month, $matches);
            $year = $matches[1];
            $month = $matches[2];
            $date->setDate($year, $month, 1);
        }

        $start = clone $date;
        $end = clone $date;

        $start->setDate($date->format('Y'), $date->format('m'), 1);
        $start->setTime(0, 0, 1);

        $end->setDate($date->format('Y'), $date->format('m'), $date->format('t'));
        $end->setTime(23, 59, 59);

        $orders = $this->getDoctrine()->getRepository(Order::class)
            ->findOrdersByRestaurantAndDateRange(
                $restaurant,
                $start,
                $end
            );

        $fulfilledOrders = array_filter($orders, function($order) {
            return $order->getState() === 'fulfilled';
        });

        $stats = new RestaurantStats($restaurant, $fulfilledOrders, $this->get('sylius.repository.tax_rate'));

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

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'stats' => $stats,
            'start' => $start,
            'end' => $end
        ], []));
    }
}
