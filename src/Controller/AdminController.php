<?php

namespace AppBundle\Controller;

use ACSEO\TypesenseBundle\Finder\CollectionFinderInterface;
use ACSEO\TypesenseBundle\Finder\TypesenseQuery;
use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\AdminDashboardTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Nonprofit;
use AppBundle\Entity\User;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Hub;
use AppBundle\Entity\Invitation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\OptinConsent;
use AppBundle\Entity\Organization;
use AppBundle\Entity\OrganizationConfig;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Entity\Vehicle;
use AppBundle\Entity\Woopit\WoopitIntegration;
use AppBundle\Entity\Zone;
use AppBundle\Form\AddOrganizationType;
use AppBundle\Form\AttachToOrganizationType;
use AppBundle\Form\ApiAppType;
use AppBundle\Form\BannerType;
use AppBundle\Form\CustomizeType;
use AppBundle\Form\DataExportType;
use AppBundle\Form\DeliveryImportType;
use AppBundle\Form\EmbedSettingsType;
use AppBundle\Form\GeoJSONUploadType;
use AppBundle\Form\HubType;
use AppBundle\Form\WoopitIntegrationType;
use AppBundle\Form\InviteUserType;
use AppBundle\Form\MaintenanceType;
use AppBundle\Form\MercadopagoLivemodeType;
use AppBundle\Form\NewOrderType;
use AppBundle\Form\NonprofitType;
use AppBundle\Form\OrderType;
use AppBundle\Form\OrganizationType;
use AppBundle\Form\PackageSetType;
use AppBundle\Form\PricingRuleSetType;
use AppBundle\Form\RestaurantAdminType;
use AppBundle\Form\SettingsType;
use AppBundle\Form\StripeLivemodeType;
use AppBundle\Form\Type\TimeSlotChoiceType;
use AppBundle\Form\Sylius\Promotion\CreditNoteType;
use AppBundle\Form\TimeSlotType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\UsersExportType;
use AppBundle\Form\VehicleType;
use AppBundle\Form\ZoneCollectionType;
use AppBundle\Service\ActivityManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\EmailManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TagManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Query\Expr;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Nucleos\UserBundle\Util\TokenGeneratorInterface;
use Nucleos\UserBundle\Util\CanonicalizerInterface;
use Nucleos\ProfileBundle\Mailer\Mail\RegistrationMail;
use Knp\Component\Pager\PaginatorInterface;
use Ramsey\Uuid\Uuid;
use Redis;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client as OAuth2Client;
use Twig\Environment as TwigEnvironment;

class AdminController extends AbstractController
{
    const ITEMS_PER_PAGE = 20;

    use AccessControlTrait;
    use AdminDashboardTrait;
    use DeliveryTrait;
    use OrderTrait;
    use RestaurantTrait;
    use StoreTrait;
    use UserTrait;

    protected function getRestaurantRoutes()
    {
        return [
            'restaurants' => 'admin_restaurants',
            'restaurant' => 'admin_restaurant',
            'menu_taxons' => 'admin_restaurant_menu_taxons',
            'menu_taxon' => 'admin_restaurant_menu_taxon',
            'products' => 'admin_restaurant_products',
            'product_options' => 'admin_restaurant_product_options',
            'product_new' => 'admin_restaurant_product_new',
            'dashboard' => 'admin_restaurant_dashboard',
            'planning' => 'admin_restaurant_planning',
            'stripe_oauth_redirect' => 'admin_restaurant_stripe_oauth_redirect',
            'preparation_time' => 'admin_restaurant_preparation_time',
            'stats' => 'admin_restaurant_stats',
            'deposit_refund' => 'admin_restaurant_deposit_refund',
            'promotions' => 'admin_restaurant_promotions',
            'promotion_new' => 'admin_restaurant_new_promotion',
            'promotion' => 'admin_restaurant_promotion',
            'product_option_preview' => 'admin_restaurant_product_option_preview',
            'reusable_packaging_new' => 'admin_restaurant_new_reusable_packaging',
            'mercadopago_oauth_redirect' => 'admin_restaurant_mercadopago_oauth_redirect',
            'mercadopago_oauth_remove' => 'admin_restaurant_mercadopago_oauth_remove',
        ];
    }

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        PromotionCouponRepositoryInterface $promotionCouponRepository,
        FactoryInterface $promotionRuleFactory,
        FactoryInterface $promotionFactory,
        HttpClientInterface $browserlessClient,
        bool $optinExportUsersEnabled,
        CollectionFinderInterface $typesenseShopsFinder,
        bool $adhocOrderEnabled
    )
    {
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
        $this->promotionCouponRepository = $promotionCouponRepository;
        $this->promotionRuleFactory = $promotionRuleFactory;
        $this->promotionFactory = $promotionFactory;
        $this->browserlessClient = $browserlessClient;
        $this->optinExportUsersEnabled = $optinExportUsersEnabled;
        $this->adhocOrderEnabled = $adhocOrderEnabled;
        $this->typesenseShopsFinder = $typesenseShopsFinder;
    }

    /**
     * @Route("/admin", name="admin_index")
     */
    public function indexAction()
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    protected function getOrderList(Request $request, $showCanceled = false)
    {
        $qb = $this->orderRepository
            ->createQueryBuilder('o');
        $qb
            ->andWhere('o.state != :state')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->orderBy('LOWER(o.shippingTimeRange)', 'DESC')
            ->setFirstResult(($request->query->getInt('p', 1) - 1) * self::ITEMS_PER_PAGE)
            ->setMaxResults(self::ITEMS_PER_PAGE)
            ;

        if (!$showCanceled) {
            $qb
                ->andWhere('o.state != :state_cancelled')
                ->setParameter('state_cancelled', OrderInterface::STATE_CANCELLED);
        }

        $paginator = new Paginator($qb->getQuery());
        $count = count($paginator);

        $orders = $paginator->getIterator();
        $pages  = ceil($count / self::ITEMS_PER_PAGE);
        $page   = $request->query->get('p', 1);

        return [ $orders, $pages, $page ];
    }

    /**
     * @Route("/admin/orders/search", name="admin_orders_search")
     */
    public function searchOrdersAction(
        Request $request,
        OrderRepository $orderRepository
    )
    {
        $results = $orderRepository->search($request->query->get('q'));

        $data = [];
        foreach ($results as $order) {
            $data[] = [
                'id' => $order->getId(),
                'name' => sprintf(
                    '%s (%s)',
                    $order->getNumber(),
                    $order->getCustomer()->getEmailCanonical()
                ),
                'path' => $this->generateUrl('admin_order', ['id' => $order->getId()]),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/admin/orders/{id}", name="admin_order")
     */
    public function orderAction(
        $id,
        Request $request,
        OrderManager $orderManager,
        DeliveryManager $deliveryManager,
        EmailManager $emailManager
    )
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $emailForm = $this->createFormBuilder([])
            ->add('email', EmailType::class)
            ->getForm();

        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $email = $emailForm->get('email')->getData();

            $message = $emailManager->createOrderPaymentMessage($order);

            $emailManager->sendTo($message, $email);

            $this->addFlash(
                'notice',
                $this->translator->trans('orders.payment_link.sent')
            );

            return $this->redirectToRoute('admin_order', ['id' => $id]);
        }

        $form = $this->createForm(OrderType::class, $order);

        $form->handleRequest($request);

        foreach ($form->get('payments') as $paymentForm) {
            if ($paymentForm->isSubmitted() && $paymentForm->isValid()) {
                $hasClickedRefund =
                    $paymentForm->getClickedButton() && 'refund' === $paymentForm->getClickedButton()->getName();

                $hasExpectedFields = $paymentForm->has('amount');

                if ($hasClickedRefund && $hasExpectedFields) {
                    $payment = $paymentForm->getData();
                    $amount = $paymentForm->get('amount')->getData();
                    $liableParty = $paymentForm->get('liable')->getData();
                    $comments = $paymentForm->get('comments')->getData();

                    $orderManager->refundPayment($payment, $amount, $liableParty, $comments);

                    $this->entityManager->flush();

                    $this->addFlash(
                        'notice',
                        $this->translator->trans('orders.payment_refunded')
                    );

                    return $this->redirectToRoute('admin_order', ['id' => $id]);
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->getClickedButton()) {
                if ('accept' === $form->getClickedButton()->getName()) {
                    $orderManager->accept($order);
                }

                if ('fulfill' === $form->getClickedButton()->getName()) {
                    if ($order->hasVendor()) {
                        throw new BadRequestHttpException(sprintf('Order #%d should not be fulfilled directly', $order->getId()));
                    }

                    $orderManager->fulfill($order);
                }

                if ('refuse' === $form->getClickedButton()->getName()) {
                    $orderManager->refuse($order);
                }

                if ('cancel' === $form->getClickedButton()->getName()) {
                    $orderManager->cancel($order);
                }

                $this->entityManager->flush();

                return $this->redirectToRoute('admin_orders');
            }
        }

        // When the order is in state "new", it does not have a delivery
        $delivery = $order->getDelivery();
        if (!$order->isTakeaway() && null === $delivery) {
            $delivery = $deliveryManager->createFromOrder($order);
        }

        return $this->render('order/service.html.twig', [
            'layout' => 'admin.html.twig',
            'order' => $order,
            'delivery' => $delivery,
            'form' => $form->createView(),
            'email_form' => $emailForm->createView(),
        ]);
    }

    public function foodtechDashboardAction($date, Request $request, Redis $redis, IriConverterInterface $iriConverter)
    {
        if ($request->query->has('order')) {
            $order = $request->query->get('order');
            if (is_numeric($order)) {
                return $this->redirectToRoute($request->attributes->get('_route'), [
                    'date' => $date,
                    'order' => $iriConverter->getItemIriFromResourceClass(Order::class, [$order])
                ], 301);
            }
        }

        $date = new \DateTime($date);

        $orders = $this->orderRepository->findOrdersByDate($date);

        $ordersNormalized = $this->get('serializer')->normalize($orders, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order_minimal']
        ]);

        $preparationDelay = $redis->get('foodtech:preparation_delay');
        if (!$preparationDelay) {
            $preparationDelay = 0;
        }

        return $this->render('admin/foodtech_dashboard.html.twig', [
            'orders' => $orders,
            'date' => $date,
            'orders_normalized' => $ordersNormalized,
            'initial_order' => $request->query->get('order'),
            'routes' => $request->attributes->get('routes'),
            'preparation_delay' => intval($preparationDelay),
        ]);
    }

    public function foodtechSettingsAction(Request $request, Redis $redis)
    {
        $preparationDelay = $request->request->get('preparation_delay');
        if (0 === $preparationDelay) {
            $redis->del('foodtech:preparation_delay');
        } else {
            $redis->set('foodtech:preparation_delay', $preparationDelay);
        }

        return new JsonResponse([
            'preparation_delay' => $preparationDelay,
        ]);
    }

    /**
     * @Route("/admin/users", name="admin_users")
     */
    public function usersAction(Request $request, PaginatorInterface $paginator)
    {
        $qb = $this->getDoctrine()
            ->getRepository(Customer::class)
            ->createQueryBuilder('c');

        $qb->leftJoin(User::class, 'u', Expr\Join::WITH, 'c.id = u.customer');

        if (!$request->query->has('filterField') || $request->query->get('filterField') !== 'u.enabled') {
            $qb->andWhere('u.enabled = :enabled');
            $qb->setParameter('enabled', true);
        }

        $customers = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 'c.id',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
                PaginatorInterface::SORT_FIELD_ALLOW_LIST => ['u.username', 'c.id'],
                PaginatorInterface::FILTER_FIELD_ALLOW_LIST => ['u.roles', 'u.username', 'u.enabled']
            ]
        );

        $attributes = [];

        foreach ($customers as $customer) {
            $key = $customer->getEmailCanonical();

            $qb = $this->orderRepository->createQueryBuilder('o');
            $qb->andWhere('o.customer = :customer');
            $qb->andWhere('o.state != :state');
            $qb->setParameter('customer', $customer);
            $qb->setParameter('state', OrderInterface::STATE_CART);

            $res = $qb->getQuery()->getResult();

            $attributes[$key]['orders_count'] = count($res);

            $qb = $this->orderRepository->createQueryBuilder('o');
            $qb->andWhere('o.customer = :customer');
            $qb->andWhere('o.state != :state');
            $qb->setParameter('customer', $customer);
            $qb->setParameter('state', OrderInterface::STATE_CART);
            $qb->orderBy('o.updatedAt', 'DESC');
            $qb->setMaxResults(1);

            $res = $qb->getQuery()->getOneOrNullResult();

            $attributes[$key]['last_order'] = $res;
        }

        $parameters = [
            'customers' => $customers,
            'attributes' => $attributes,
            'optin_export_users_enabled' => $this->optinExportUsersEnabled,
        ];

        if ($this->optinExportUsersEnabled) {

            $usersExportForm = $this->createForm(UsersExportType::class);
            $usersExportForm->handleRequest($request);

            if ($usersExportForm->isSubmitted() && $usersExportForm->isValid()) {
                $optinSelected = $usersExportForm->get('optins')->getData();

                $optinsQB = $this->getDoctrine()
                    ->getRepository(User::class)
                    ->createQueryBuilder('u')
                    ->select('u.username, u.email')
                    ->innerJoin('u.optinConsents', 'oc')
                    ->where('oc.type = :optin and oc.accepted = true')
                    ->setParameter('optin', $optinSelected);

                $optinsResult = $optinsQB->getQuery()->getResult();

                $csv = $this->get('serializer')->serialize($optinsResult, 'csv');

                $filename = sprintf('coopcycle-users-for-%s-.csv', $optinSelected);

                $response = new Response($csv);
                $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $filename
                ));

                return $response;
            }

            $parameters['users_export_form'] = $usersExportForm->createView();
        }

        return $this->render('admin/users.html.twig', $parameters);
    }

    /**
     * @Route("/admin/users/invite", name="admin_users_invite")
     */
    public function inviteUserAction(
        Request $request,
        EmailManager $emailManager,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $objectManager,
        CanonicalizerInterface $canonicalizer
    )
    {
        $form = $this->createForm(InviteUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invitation = $form->getData();

            $roles = $form->get('roles')->getData();
            $restaurants = $form->get('restaurants')->getData();
            $stores = $form->get('stores')->getData();

            foreach ($roles as $role) {
                $invitation->addRole($role);
            }

            foreach ($restaurants as $restaurant) {
                $invitation->addRestaurant($restaurant);
                $invitation->addRole('ROLE_RESTAURANT');
            }

            foreach ($stores as $store) {
                $invitation->addStore($store);
                $invitation->addRole('ROLE_STORE');
            }

            // TODO Check if already invited
            // TODO Check if same email already exists

            $invitation->setEmail($canonicalizer->canonicalize($invitation->getEmail()));
            $invitation->setUser($this->getUser());
            $invitation->setCode($tokenGenerator->generateToken());

            $objectManager->persist($invitation);
            $objectManager->flush();

            // Send invitation email
            $message = $emailManager->createInvitationMessage($invitation);
            $emailManager->sendTo($message, $invitation->getEmail());
            $invitation->setSentAt(new \DateTime());

            $objectManager->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('basics.send_invitation.confirm')
            );

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_invite.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/user/{username}", name="admin_user_details")
     */
    public function userAction($username, Request $request, UserManagerInterface $userManager)
    {
        $user = $userManager->findUserByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/user.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @Route("/admin/user/{username}/edit", name="admin_user_edit")
     */
    public function userEditAction($username, Request $request, UserManagerInterface $userManager)
    {
        $user = $userManager->findUserByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        // Roles that can be edited by admin
        $editableRoles = ['ROLE_ADMIN', 'ROLE_COURIER', 'ROLE_RESTAURANT', 'ROLE_STORE'];

        $originalRoles = array_filter($user->getRoles(), function ($role) use ($editableRoles) {
            return in_array($role, $editableRoles);
        });

        $editForm = $this->createForm(UpdateProfileType::class, $user, [
            'with_restaurants' => true,
            'with_stores' => true,
            'with_roles' => true,
            'editable_roles' => $editableRoles
        ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $user = $editForm->getData();

            $roles = $editForm->get('roles')->getData();

            $rolesToRemove = array_diff($originalRoles, $roles);

            foreach ($rolesToRemove as $role) {
                $user->removeRole($role);
            }

            foreach ($roles as $role) {
                if (!$user->hasRole($role)) {
                    $user->addRole($role);
                }
            }

            $userManager->updateUser($user);

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_user_edit', ['username' => $user->getUsername()]);
        }

        return $this->render('admin/user_edit.html.twig', [
            'form' => $editForm->createView(),
            'user' => $user,
        ]);
    }

    /**
     * @Route("/admin/user/{username}/delete", name="admin_user_delete", methods={"POST"})
     */
    public function userDeleteAction($username, Request $request, UserManagerInterface $userManager)
    {
        $user = $userManager->findUserByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        $userManager->deleteUser($user);

        $this->addFlash(
            'notice',
            $this->translator->trans('adminDashboard.users.userHasBeenDeleted')
        );

        return $this->redirectToRoute('admin_users');
    }

    /**
     * @Route("/admin/user/{username}/tracking", name="admin_user_tracking")
     */
    public function userTrackingAction($username, Request $request, UserManagerInterface $userManager)
    {
        $user = $userManager->findUserByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->userTracking($user, 'admin');
    }

    protected function getRestaurantList(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(LocalBusiness::class);

        $countAll = $repository
            ->createQueryBuilder('r')->select('COUNT(r)')
            ->getQuery()->getSingleScalarResult();

        $pages = ceil($countAll / self::ITEMS_PER_PAGE);
        $page = $request->query->getInt('p', 1);

        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $restaurants = $repository->findBy([], [
            'enabled' => 'DESC',
            'id' => 'DESC',
        ], self::ITEMS_PER_PAGE, $offset);

        return [ $restaurants, $pages, $page ];
    }

    /**
     * @Route("/admin/deliveries", name="admin_deliveries")
     */
    public function deliveriesAction(Request $request,
        PaginatorInterface $paginator,
        DeliveryManager $deliveryManager,
        OrderFactory $orderFactory,
        OrderManager $orderManager)
    {
        $deliveryImportForm = $this->createForm(DeliveryImportType::class, null, [
            'with_store' => true
        ]);

        $deliveryImportForm->handleRequest($request);
        if ($deliveryImportForm->isSubmitted() && $deliveryImportForm->isValid()) {
            $store = $deliveryImportForm->get('store')->getData();

            return $this->handleDeliveryImportForStore($store, $deliveryImportForm, 'admin_deliveries',
                $orderManager, $deliveryManager, $orderFactory,);
        }

        $dataExportForm = $this->createForm(DataExportType::class);

        $dataExportForm->handleRequest($request);
        if ($dataExportForm->isSubmitted() && $dataExportForm->isValid()) {

            $data = $dataExportForm->getData();

            $response = new Response($data['content']);

            $response->headers->add(['Content-Type' => $data['content_type']]);
            $response->headers->add([
                'Content-Disposition' => $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $data['filename']
                )
            ]);

            return $response;
        }

        $sections = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->getSections();

        $qb = $sections['past'];

        // Allow filtering by store & restaurant with KnpPaginator
        $qb->leftJoin(Store::class, 's', Expr\Join::WITH, 's.id = d.store');
        $qb->leftJoin(Order::class, 'o', Expr\Join::WITH, 'o.id = d.order');
        $qb->leftJoin(OrderVendor::class, 'v', Expr\Join::WITH, 'o.id = v.order');
        $qb->leftJoin(LocalBusiness::class, 'r', Expr\Join::WITH, 'v.restaurant = r.id');

        $deliveries = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 'd.createdAt',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
                PaginatorInterface::SORT_FIELD_ALLOW_LIST => ['d.createdAt'],
                PaginatorInterface::DEFAULT_FILTER_FIELDS => ['s.id', 'r.id'],
                PaginatorInterface::FILTER_FIELD_ALLOW_LIST => ['s.id', 'r.id']
            ]
        );

        return $this->render('admin/deliveries.html.twig', [
            'today' => $sections['today']->getQuery()->getResult(),
            'upcoming' => $sections['upcoming']->getQuery()->getResult(),
            'deliveries' => $deliveries,
            'routes' => $this->getDeliveryRoutes(),
            'stores' => $this->getDoctrine()->getRepository(Store::class)->findBy([], ['name' => 'ASC']),
            'delivery_import_form' => $deliveryImportForm->createView(),
            'delivery_export_form' => $dataExportForm->createView(),
        ]);
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'      => 'admin_deliveries',
            'pick'      => 'admin_delivery_pick',
            'deliver'   => 'admin_delivery_deliver',
            'view'      => 'admin_delivery',
            'store_new' => 'admin_store_delivery_new',
            'store_addresses' => 'admin_store_addresses',
            'download_images' => 'admin_store_delivery_download_images',
        ];
    }

    /**
     * @Route("/admin/tasks", name="admin_tasks")
     */
    public function tasksAction(Request $request, PaginatorInterface $paginator)
    {
        $form = $this->createForm(AttachToOrganizationType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tasks = $form->get('tasks')->getData();
            $store = $form->get('store')->getData();

            if ($store) {
                foreach ($tasks as $task) {
                    if (null === $task->getOrganization()) {
                        $task->setOrganization($store->getOrganization());
                    }
                }

                $this->getDoctrine()->getManagerForClass(Task::class)->flush();
            }

            return $this->redirectToRoute('admin_tasks');
        }

        $qb = $this->getDoctrine()
            ->getRepository(Task::class)
            ->createQueryBuilder('t');

        $tasks = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 't.doneBefore',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
                PaginatorInterface::SORT_FIELD_ALLOW_LIST => ['t.doneBefore'],
                PaginatorInterface::DEFAULT_FILTER_FIELDS => [],
                PaginatorInterface::FILTER_FIELD_ALLOW_LIST => []
            ]
        );

        return $this->render('admin/tasks.html.twig', [
            'tasks' => $tasks,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/settings/taxation", name="admin_taxation_settings")
     */
    public function taxationSettingsAction(
        Request $request,
        TaxRateResolverInterface $taxRateResolver,
        TaxCategoryRepositoryInterface $taxCategoryRepository
    )
    {
        $categories = [];
        $countries = [];

        $taxCategories = $taxCategoryRepository->findBy([], ['name' => 'ASC']);
        foreach ($taxCategories as $c) {
            $isLegacy = count($c->getRates()) === 1 && null === $c->getRates()->first()->getCountry();
            if (!$isLegacy) {
                foreach ($c->getRates() as $r) {
                    $countries[] = $r->getCountry();
                }
            }

            if ($isLegacy) {
                continue;
            }

            $rates = [];
            foreach ($c->getRates() as $rate) {
                $rates[$rate->getCountry()][] = $rate;
            }

            $categories[] = [
                'name' => $this->translator->trans($c->getName(), [], 'taxation'),
                'rates' => $rates,
            ];
        }

        return $this->render('admin/taxation_settings.html.twig', [
            'categories' => $categories,
            'countries' => array_unique($countries),
        ]);
    }

    /**
     * @Route("/admin/settings/tags", name="admin_tags")
     */
    public function tagsAction(Request $request, TagManager $tagManager)
    {
        if ($request->isMethod('POST') && $request->request->has('delete')) {
            $id = $request->request->get('tag');
            $tag = $this->getDoctrine()->getRepository(Tag::class)->find($id);
            $tagManager->untagAll($tag);
            $this->getDoctrine()->getManagerForClass(Tag::class)->remove($tag);
            $this->getDoctrine()->getManagerForClass(Tag::class)->flush();

            return  $this->redirectToRoute('admin_tags');
        }

        if ($request->query->has('format')) {
            if ('json' === $request->query->get('format')) {

                return new JsonResponse($tagManager->getAllTags());
            }
        }

        $tags = $this->getDoctrine()->getRepository(Tag::class)->findAll();

        return $this->render('admin/tags.html.twig', [
            'tags' => $tags
        ]);
    }

    /**
     * @Route("/admin/deliveries/pricing", name="admin_deliveries_pricing")
     */
    public function pricingRuleSetsAction()
    {
        $ruleSets = $this->getDoctrine()
            ->getRepository(Delivery\PricingRuleSet::class)
            ->findAll();
        return $this->render('admin/pricing.html.twig', [
            'ruleSets' => $ruleSets
        ]);
    }

    private function renderPricingRuleSetForm(Delivery\PricingRuleSet $ruleSet, Request $request)
    {
        $originalRules = new ArrayCollection();

        foreach ($ruleSet->getRules() as $rule) {
            $originalRules->add($rule);
        }

        $packageSets = $this->getDoctrine()->getRepository(PackageSet::class)->findAll();
        $packageNames = [];
        foreach ($packageSets as $packageSet) {
            foreach ($packageSet->getPackages() as $package) {
                $packageNames[] = $package->getName();
            }
        }

        $form = $this->createForm(PricingRuleSetType::class, $ruleSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $ruleSet = $form->getData();

            $em = $this->getDoctrine()->getManagerForClass(Delivery\PricingRule::class);

            foreach ($originalRules as $originalRule) {
                if (!$ruleSet->getRules()->contains($originalRule)) {
                    $em->remove($originalRule);
                }
            }

            foreach ($ruleSet->getRules() as $rule) {
                $rule->setRuleSet($ruleSet);
            }

            if (null === $ruleSet->getId()) {
                $em->persist($ruleSet);
            }

            $em->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_deliveries_pricing_ruleset', ['id' => $ruleSet->getId()]);
        }

        return $this->render('admin/pricing_rule_set.html.twig', [
            'form' => $form->createView(),
            'packages' => $packageNames,
        ]);
    }

    /**
     * @Route("/admin/deliveries/pricing/new", name="admin_deliveries_pricing_ruleset_new")
     */
    public function newPricingRuleSetAction(Request $request)
    {
        $ruleSet = new Delivery\PricingRuleSet();

        return $this->renderPricingRuleSetForm($ruleSet, $request);
    }

    /**
     * @Route("/admin/deliveries/pricing/{id}", name="admin_deliveries_pricing_ruleset")
     */
    public function pricingRuleSetAction($id, Request $request)
    {
        $ruleSet = $this->getDoctrine()
            ->getRepository(Delivery\PricingRuleSet::class)
            ->find($id);

        return $this->renderPricingRuleSetForm($ruleSet, $request);
    }

    /**
     * @Route("/admin/deliveries/pricing/{id}/duplicate", name="admin_deliveries_pricing_ruleset_duplicate")
     */
    public function duplicatePricingRuleSetAction($id, Request $request)
    {
        $ruleSet = $this->getDoctrine()
            ->getRepository(Delivery\PricingRuleSet::class)
            ->find($id);

        $duplicated = $ruleSet->duplicate($this->translator);

        return $this->renderPricingRuleSetForm($duplicated, $request);
    }

    /**
     * @Route("/admin/zones/{id}/delete", methods={"POST"}, name="admin_zone_delete")
     */
    public function deleteZoneAction($id)
    {
        $zone = $this->getDoctrine()->getRepository(Zone::class)->find($id);

        $this->getDoctrine()->getManagerForClass(Zone::class)->remove($zone);
        $this->getDoctrine()->getManagerForClass(Zone::class)->flush();

        return $this->redirectToRoute('admin_zones');
    }

    /**
     * @Route("/admin/zones", name="admin_zones")
     */
    public function zonesAction(Request $request)
    {
        $zoneCollection = new \stdClass();
        $zoneCollection->zones = [];

        $uploadForm = $this->createForm(GeoJSONUploadType::class);
        $zoneCollectionForm = $this->createForm(ZoneCollectionType::class, $zoneCollection);

        $zoneCollectionForm->handleRequest($request);
        if ($zoneCollectionForm->isSubmitted() && $zoneCollectionForm->isValid()) {
            $zoneCollection = $zoneCollectionForm->getData();

            foreach ($zoneCollection->zones as $zone) {
                $this->getDoctrine()->getManagerForClass(Zone::class)->persist($zone);
            }

            $this->getDoctrine()->getManagerForClass(Zone::class)->flush();

            return $this->redirectToRoute('admin_zones');
        }

        $uploadForm->handleRequest($request);
        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $geojson = $uploadForm->getData();
            foreach ($geojson as $feature) {
                $zone = new Zone();
                $zone->setGeoJSON($feature->getGeometry()->jsonSerialize());
                $zoneCollection->zones[] = $zone;
            }

            $zoneCollectionForm->setData($zoneCollection);
        }

        $zones = $this->getDoctrine()->getRepository(Zone::class)->findAll();

        return $this->render('admin/zones.html.twig', [
            'zones' => $zones,
            'upload_form' => $uploadForm->createView(),
            'zone_collection_form' => $zoneCollectionForm->createView(),
        ]);
    }

    public function newStoreAction(Request $request)
    {
        $store = new Store();

        return $this->renderStoreForm($store, $request, $this->translator);
    }

    /**
     * @Route("/admin/restaurants/search", name="admin_restaurants_search")
     */
    public function searchRestaurantsAction(Request $request)
    {
        $query = new TypesenseQuery($request->query->get('q'), 'name');

        $results = $this->typesenseShopsFinder->rawQuery($query)->getResults();

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {
            $data = array_map(function ($hit) {
                return [
                    'id' => $hit['document']['id'],
                    'name' => $hit['document']['name'],
                ];
            }, $results);

            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/admin/stores/search", name="admin_stores_search")
     */
    public function searchStoresAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Store::class);

        $qb = $repository->createQueryBuilder('s');
        $qb
            ->where('LOWER(s.name) LIKE :q')
            ->setParameter('q', '%' . strtolower($request->query->get('q')) . '%');

        $results = $qb->getQuery()->getResult();

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {
            $data = array_map(function (Store $store) {
                return [
                    'id' => $store->getId(),
                    'name' => $store->getName(),
                    'pricingRuleSetId' => $store->getPricingRuleSet() ? $store->getPricingRuleSet()->getId() : null,
                    'address' => [
                        'addressLocality' => $store->getAddress()->getAddressLocality(),
                        'addressCountry' => $store->getAddress()->getAddressCountry(),
                        'streetAddress' => $store->getAddress()->getStreetAddress(),
                        'postalCode' => $store->getAddress()->getPostalCode(),
                        'latitude' => $store->getAddress()->getGeo()->getLatitude(),
                        'longitude' => $store->getAddress()->getGeo()->getLongitude()
                    ]
                ];
            }, $results);

            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/admin/users/search", name="admin_users_search")
     */
    public function searchUsersAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(User::class);

        $results = $repository->search($request->query->get('q'));

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {
            $data = array_map(function (User $user) {
                $text = sprintf('%s (%s)', $user->getEmail(), $user->getUsername());

                return [
                    'id' => $user->getId(),
                    'name' => $text,
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'firstName' => $user->getGivenName(),
                    'lastName' => $user->getFamilyName(),
                ];
            }, $results);

            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/admin/settings", name="admin_settings")
     */
    public function settingsAction(Request $request, SettingsManager $settingsManager, Redis $redis)
    {
        /* Stripe live mode */

        $isStripeLivemode = $settingsManager->isStripeLivemode();
        $canEnableStripeLivemode = $settingsManager->canEnableStripeLivemode();
        $stripeLivemodeForm = $this->createForm(StripeLivemodeType::class);

        $stripeLivemodeForm->handleRequest($request);
        if ($stripeLivemodeForm->isSubmitted() && $stripeLivemodeForm->isValid()) {
            if ($stripeLivemodeForm->getClickedButton()) {
                if ('enable' === $stripeLivemodeForm->getClickedButton()->getName()) {
                    $settingsManager->set('stripe_livemode', 'yes');
                }
                if ('disable' === $stripeLivemodeForm->getClickedButton()->getName()) {
                    $settingsManager->set('stripe_livemode', 'no');
                }
                if ('disable_and_enable_maintenance' === $stripeLivemodeForm->getClickedButton()->getName()) {
                    $redis->set('maintenance', '1');
                    $settingsManager->set('stripe_livemode', 'no');
                }
                $settingsManager->flush();
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Mercadopago live mode */

        $isMercadopagoLivemode = $settingsManager->isMercadopagoLivemode();
        $canEnableMercadopagoLivemode = $settingsManager->canEnableMercadopagoLivemode();
        $mercadopagoLivemodeForm = $this->createForm(MercadopagoLivemodeType::class);

        $mercadopagoLivemodeForm->handleRequest($request);
        if ($mercadopagoLivemodeForm->isSubmitted() && $mercadopagoLivemodeForm->isValid()) {
            if ($mercadopagoLivemodeForm->getClickedButton()) {
                if ('enable' === $mercadopagoLivemodeForm->getClickedButton()->getName()) {
                    $settingsManager->set('mercadopago_livemode', 'yes');
                }
                if ('disable' === $mercadopagoLivemodeForm->getClickedButton()->getName()) {
                    $settingsManager->set('mercadopago_livemode', 'no');
                }
                if ('disable_and_enable_maintenance' === $mercadopagoLivemodeForm->getClickedButton()->getName()) {
                    $redis->set('maintenance', '1');
                    $settingsManager->set('mercadopago_livemode', 'no');
                }
                $settingsManager->flush();
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Maintenance */

        $maintenanceForm = $this->createForm(MaintenanceType::class);

        $maintenanceForm->handleRequest($request);
        if ($maintenanceForm->isSubmitted() && $maintenanceForm->isValid()) {
            if ($maintenanceForm->getClickedButton()) {
                if ('enable' === $maintenanceForm->getClickedButton()->getName()) {
                    $maintenanceMessage = $maintenanceForm->get('message')->getData();

                    $redis->set('maintenance_message', $maintenanceMessage);
                    $redis->set('maintenance', '1');
                }
                if ('disable' === $maintenanceForm->getClickedButton()->getName()) {
                    $redis->del('maintenance_message');
                    $redis->del('maintenance');
                }
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Banner */

        $bannerForm = $this->createForm(BannerType::class);

        $bannerForm->handleRequest($request);
        if ($bannerForm->isSubmitted() && $bannerForm->isValid()) {
            if ($bannerForm->getClickedButton()) {
                if ('enable' === $bannerForm->getClickedButton()->getName()) {
                    $bannerMessage = $bannerForm->get('message')->getData();

                    $redis->set('banner_message', $bannerMessage);
                    $redis->set('banner', '1');
                }
                if ('disable' === $bannerForm->getClickedButton()->getName()) {
                    $redis->del('banner_message');
                    $redis->del('banner');
                }
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Settings */

        $settings = $settingsManager->asEntity();
        $form = $this->createForm(SettingsType::class, $settings);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            foreach ($data as $name => $value) {
                $settingsManager->set($name, $value);
            }

            $settingsManager->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings.html.twig', [
            'timezone' => ini_get('date.timezone'),
            'form' => $form->createView(),
            'maintenance_form' => $maintenanceForm->createView(),
            'maintenance' => $redis->get('maintenance'),
            'banner_form' => $bannerForm->createView(),
            'banner' => $redis->get('banner'),
            'stripe_livemode' => $isStripeLivemode,
            'stripe_livemode_form' => $stripeLivemodeForm->createView(),
            'can_enable_stripe_livemode' => $canEnableStripeLivemode,
            'mercadopago_livemode' => $isMercadopagoLivemode,
            'mercadopago_livemode_form' => $mercadopagoLivemodeForm->createView(),
            'can_enable_mercadopago_livemode' => $canEnableMercadopagoLivemode,
        ]);
    }
    /**
     * @Route("/admin/embed", name="admin_embed")
     */
    public function embedAction()
    {
        return $this->redirectToRoute('admin_forms', [], 301);
    }

    /**
     * @Route("/admin/forms/new", name="admin_form_new")
     */
    public function newFormAction(Request $request)
    {
        $deliveryForm = new DeliveryForm();
        $form = $this->createForm(EmbedSettingsType::class, $deliveryForm);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()
                ->getManagerForClass(DeliveryForm::class)
                ->persist($deliveryForm);
            $this->getDoctrine()
                ->getManagerForClass(DeliveryForm::class)
                ->flush();

            return $this->redirectToRoute('admin_forms');
        }

        return $this->render('admin/embed.html.twig', [
            'embed_settings_form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/forms/{id}", name="admin_form")
     */
    public function formAction($id, Request $request)
    {
        $deliveryForm = $this->getDoctrine()->getRepository(DeliveryForm::class)->find($id);

        $form = $this->createForm(EmbedSettingsType::class, $deliveryForm);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()
                ->getManagerForClass(DeliveryForm::class)
                ->flush();

            return $this->redirectToRoute('admin_forms');
        }

        return $this->render('admin/embed.html.twig', [
            'embed_settings_form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/forms", name="admin_forms")
     */
    public function formsAction()
    {
        $forms = $this->getDoctrine()->getRepository(DeliveryForm::class)->findAll();
        return $this->render('admin/forms.html.twig', [
            'forms' => $forms,
        ]);
    }

    /**
     * @Route("/admin/activity", name="admin_activity")
     */
    public function activityAction(Request $request, ActivityManager $activityManager)
    {
        $date = new \DateTime();
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }

        $events = $activityManager->getEventsByDate($date);

        return $this->render('admin/activity.html.twig', [
            'events' => $events,
            'date' => $date
        ]);
    }

    /**
     * @Route("/admin/api/apps", name="admin_api_apps")
     */
    public function apiAppsAction(Request $request)
    {
        if ($request->isMethod('POST') && $request->request->has('oauth2_client')) {

            $oAuth2ClientId = $request->get('oauth2_client');
            $oAuth2Client = $this->entityManager
                ->getRepository(OAuth2Client::class)
                ->find($oAuth2ClientId);

            $newSecret = hash('sha512', random_bytes(32));
            $oAuth2Client->setSecret($newSecret);

            $this->entityManager->flush();

            return $this->redirectToRoute('admin_api_apps');
        }

        $qb = $this->entityManager
            ->getRepository(ApiApp::class)
            ->createQueryBuilder('a')
            ->andWhere('a.store IS NOT NULL');

        $apiApps = $qb->getQuery()->getResult();

        return $this->render('admin/api_apps.html.twig', [
            'api_apps' => $apiApps
        ]);
    }

    /**
     * @Route("/admin/api/apps/new", name="admin_new_api_app")
     */
    public function newApiAppAction(Request $request)
    {
        $apiApp = new ApiApp();

        $form = $this->createForm(ApiAppType::class, $apiApp);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $apiApp = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(ApiApp::class)
                ->persist($apiApp);

            $this->getDoctrine()
                ->getManagerForClass(ApiApp::class)
                ->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('api_apps.created.message')
            );

            return $this->redirectToRoute('admin_api_app', [ 'id' => $apiApp->getId() ]);
        }

        return $this->render('admin/api_app_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/api/apps/{id}", name="admin_api_app")
     */
    public function apiAppAction($id, Request $request)
    {
        $apiApp = $this->getDoctrine()
            ->getRepository(ApiApp::class)
            ->find($id);

        $form = $this->createForm(ApiAppType::class, $apiApp);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $apiApp = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(ApiApp::class)
                ->flush();

            return $this->redirectToRoute('admin_api_apps');
        }

        return $this->render('admin/api_app_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/integrations", name="admin_integrations")
     */
    public function integrationsAction(Request $request)
    {
        return $this->render('admin/integrations.html.twig');
    }

    /**
     * @Route("/admin/integrations/woopit", name="admin_integrations_woopit")
     */
    public function integrationWoopitAction(Request $request)
    {
        if ($request->isMethod('POST') && $request->request->has('oauth2_client')) {

            $oAuth2ClientId = $request->get('oauth2_client');
            $oAuth2Client = $this->entityManager
                ->getRepository(OAuth2Client::class)
                ->find($oAuth2ClientId);

            $newSecret = hash('sha512', random_bytes(32));
            $oAuth2Client->setSecret($newSecret);

            $this->entityManager->flush();

            return $this->redirectToRoute('admin_integrations');
        }

        $qb = $this->entityManager
            ->getRepository(WoopitIntegration::class)
            ->createQueryBuilder('i');

        $integrations = $qb->getQuery()->getResult();

        return $this->render('_partials/integrations/woopit/list.html.twig', [
            'integrations' => $integrations
        ]);
    }

    /**
     * @Route("/admin/integrations/woopit/new", name="admin_new_integration_woopit")
     */
    public function newIntegrationAction(Request $request)
    {
        $woopitIntegration = new WoopitIntegration();

        $form = $this->createForm(WoopitIntegrationType::class, $woopitIntegration);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $woopitIntegration = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(WoopitIntegration::class)
                ->persist($woopitIntegration);

            $this->getDoctrine()
                ->getManagerForClass(WoopitIntegration::class)
                ->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('integration.created.message')
            );

            return $this->redirectToRoute('admin_integration_woopit', [ 'id' => $woopitIntegration->getId() ]);
        }

        return $this->render('_partials/integrations/woopit/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/integrtations/woopit/{id}", name="admin_integration_woopit")
     */
    public function integrationAction($id, Request $request)
    {
        $apiApp = $this->getDoctrine()
            ->getRepository(WoopitIntegration::class)
            ->find($id);

        $form = $this->createForm(WoopitIntegrationType::class, $apiApp);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $apiApp = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(WoopitIntegration::class)
                ->flush();

            return $this->redirectToRoute('admin_integrations_woopit');
        }

        return $this->render('_partials/integrations/woopit/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/promotions", name="admin_promotions")
     */
    public function promotionsAction()
    {
        $qb = $this->promotionCouponRepository->createQueryBuilder('c');
        $qb->andWhere('c.expiresAt IS NULL OR c.expiresAt > :date');
        $qb->setParameter('date', new \DateTime());
        $promotionCoupons = $qb->getQuery()->getResult();
        return $this->render('admin/promotions.html.twig', [
            'promotion_coupons' => $promotionCoupons,
        ]);
    }

    /**
     * @Route("/admin/promotions/{id}/coupons/new", name="admin_new_promotion_coupon")
     */
    public function newPromotionCouponAction(
        $id,
        Request $request,
        PromotionRepositoryInterface $promotionRepository,
        PromotionCouponFactoryInterface $promotionCouponFactory
    )
    {
        $promotion = $promotionRepository->find($id);

        $promotionCoupon = $promotionCouponFactory->createForPromotion($promotion);

        $form = $this->createForm(PromotionCouponType::class, $promotionCoupon);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $promotionCoupon = $form->getData();
            $promotion->addCoupon($promotionCoupon);

            $this->entityManager->flush();

            return $this->redirectToRoute('admin_promotions');
        }

        return $this->render('admin/promotion_coupon.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/promotions/credit-notes/new", name="admin_new_credit_note")
     */
    public function newCreditNoteAction(Request $request, PromotionCouponFactoryInterface $promotionCouponFactory)
    {
        $form = $this->createForm(CreditNoteType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $promotion = $data->toPromotion(
                $this->promotionFactory,
                $this->promotionRuleFactory,
                $this->promotionCouponRepository,
                $promotionCouponFactory
            );

            $this->entityManager->persist($promotion);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_promotions');
        }

        return $this->render('admin/promotion_credit_note.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/promotions/coupons/new", name="admin_new_promotion_coupon_from_template")
     */
    public function newPromotionCouponFromTemplateAction(
        Request $request,
        PromotionRepositoryInterface $promotionRepository,
        PromotionCouponFactoryInterface $promotionCouponFactory
    )
    {
        $template = $request->query->get('template');

        switch ($template) {
            case 'credit_note':
                return $this->newCreditNoteAction($request, $promotionCouponFactory);
            case 'free_delivery':
                $promotion = $promotionRepository->findOneByCode('FREE_DELIVERY');

                return $this->redirectToRoute('admin_new_promotion_coupon', ['id' => $promotion->getId()]);
        }

        return $this->createNotFoundException();
    }

    /**
     * @Route("/admin/promotions/{id}/coupons/{code}", name="admin_promotion_coupon")
     */
    public function promotionCouponAction($id, $code, Request $request, PromotionRepositoryInterface $promotionRepository)
    {
        $promotionCoupon = $this->promotionCouponRepository->findOneByCode($code);
        $promotion = $promotionRepository->find($id);

        $form = $this->createForm(PromotionCouponType::class, $promotionCoupon);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_promotions');
        }

        return $this->render('admin/promotion_coupon.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/orders/{id}/emails", name="admin_order_email_preview")
     */
    public function orderEmailPreviewAction($id, Request $request, EmailManager $emailManager)
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $method = 'createOrderCreatedMessageForCustomer';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }

        $message = call_user_func_array([$emailManager, $method], [$order]);

        // An email must have a "To", "Cc", or "Bcc" header."
        $message->to('dev@coopcycle.org');

        $response = new Response();
        $response->setContent((string) $message->getHtmlBody());

        return $response;
    }

    /**
     * @Route("/admin/tasks/{id}/emails", name="admin_task_email_preview")
     */
    public function taskEmailPreviewAction($id, Request $request, EmailManager $emailManager)
    {
        $task = $this->getDoctrine()->getRepository(Task::class)->find($id);

        if (!$task) {
            throw $this->createNotFoundException(sprintf('Task #%d does not exist', $id));
        }

        $method = 'createTaskCompletedMessage';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }
        $message = call_user_func_array([$emailManager, $method], [$task]);

        // An email must have a "To", "Cc", or "Bcc" header."
        $message->to('dev@coopcycle.org');

        $response = new Response();
        $response->setContent((string) $message->getHtmlBody());

        return $response;
    }

    /**
     * @Route("/admin/emails", name="admin_email_preview")
     */
    public function emailsPreviewAction(Request $request, TwigEnvironment $twig)
    {
        $bodyRenderer = new BodyRenderer($twig);

        $message = new RegistrationMail();

        $url  = $this->generateUrl(
            'nucleos_profile_registration_confirm',
            ['token' => '123456'],
        );

        $message->setUser($this->getUser());
        $message->setConfirmationUrl($url);

        // An email must have a "To", "Cc", or "Bcc" header."
        $message->to('dev@coopcycle.org');

        $bodyRenderer->render($message);

        $response = new Response();
        $response->setContent((string) $message->getHtmlBody());

        return $response;
    }

    /**
     * @Route("/admin/emails/invitation", name="admin_email_invitation_preview")
     */
    public function invitationEmailPreviewAction(Request $request, EmailManager $emailManager)
    {
        $invitation = new Invitation();
        $invitation->setUser($this->getUser());
        $invitation->setCode('123456');

        $message = $emailManager->createInvitationMessage($invitation);

        // An email must have a "To", "Cc", or "Bcc" header."
        $message->to('dev@coopcycle.org');

        $response = new Response();
        $response->setContent((string) $message->getHtmlBody());

        return $response;
    }

    /**
     * @Route("/admin/restaurants/pledges", name="admin_restaurants_pledges")
     */
    public function restaurantsPledgesListAction(Request $request, EntityManagerInterface $manager)
    {
        $pledges = $this->getDoctrine()->getRepository(Pledge::class)->findAll();

        if ($request->isMethod('POST')) {
            $id = $request->request->get('pledge');
            $pledge = $this->getDoctrine()->getRepository(Pledge::class)->find($id);
            if ($request->request->has('accept')) {
                $restaurant = $pledge->accept();
                $manager->persist($restaurant);
                $manager->flush();

                return $this->redirectToRoute('admin_restaurant', [
                    'id' => $restaurant->getId()
                ]);
            }
            if ($request->request->has('reject')) {
                $pledge->setState('refused');
                $manager->flush();
                return $this->redirectToRoute('admin_restaurants_pledges');
            }
        }

        return $this->render('admin/restaurant_pledges.html.twig', [
            'pledges' => $pledges,
        ]);
    }

    /**
     * @Route("/admin/restaurants/pledges/{id}/emails", name="admin_pledge_email_preview")
     */
    public function pledgeEmailPreviewAction($id, Request $request, EmailManager $emailManager)
    {
        $pledge = $this->getDoctrine()->getRepository(Pledge::class)->find($id);

        if (!$pledge) {
            throw $this->createNotFoundException(sprintf('Pledge #%d does not exist', $id));
        }

        $method = 'createAdminPledgeConfirmationMessage';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }
        $message = call_user_func_array([$emailManager, $method], [$pledge]);

        $response = new Response();
        $response->setContent($message->getBody());

        return $response;
    }

    /**
     * @Route("/admin/settings/time-slots", name="admin_time_slots")
     */
    public function timeSlotsAction()
    {
        $timeSlots = $this->getDoctrine()->getRepository(TimeSlot::class)->findAll();
        return $this->render('admin/time_slots.html.twig', [
            'time_slots' => $timeSlots,
        ]);
    }

    private function renderTimeSlotForm(Request $request, TimeSlot $timeSlot, EntityManagerInterface $objectManager)
    {
        $form = $this->createForm(TimeSlotType::class, $timeSlot, [
            'validation_groups' => ['last_mile']
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $objectManager->persist($timeSlot);
            $objectManager->flush();

            return $this->redirectToRoute('admin_time_slots');
        }

        return $this->render('admin/time_slot.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/settings/time-slots/new", name="admin_new_time_slot")
     */
    public function newTimeSlotAction(Request $request, EntityManagerInterface $objectManager)
    {
        $timeSlot = new TimeSlot();

        return $this->renderTimeSlotForm($request, $timeSlot, $objectManager);
    }

    /**
     * @Route("/admin/settings/time-slots/preview", name="admin_time_slot_preview")
     */
    public function timeSlotPreviewAction(Request $request, EntityManagerInterface $objectManager)
    {
        $timeSlot = new TimeSlot();

        $form = $this->createForm(TimeSlotType::class, $timeSlot);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            $timeSlot = $form->getData();

            $form = $this->createFormBuilder()
                ->add('example', TimeSlotChoiceType::class, [
                    'time_slot' => $timeSlot,
                ])
                ->getForm();

            return $this->render('admin/time_slot_preview.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return new Response('', 200);
    }

    /**
     * @Route("/admin/settings/time-slots/{id}", name="admin_time_slot")
     */
    public function timeSlotAction($id, Request $request, EntityManagerInterface $objectManager)
    {
        $timeSlot = $this->getDoctrine()->getRepository(TimeSlot::class)->find($id);

        if (!$timeSlot) {
            throw $this->createNotFoundException(sprintf('Time slot #%d does not exist', $id));
        }

        return $this->renderTimeSlotForm($request, $timeSlot, $objectManager);
    }

    /**
     * @Route("/admin/settings/packages", name="admin_packages")
     */
    public function packageSetsAction()
    {
        $packageSets = $this->getDoctrine()->getRepository(PackageSet::class)->findAll();
        return $this->render('admin/package_sets.html.twig', [
            'package_sets' => $packageSets,
        ]);
    }

    private function renderPackageSetForm(Request $request, PackageSet $packageSet, EntityManagerInterface $objectManager)
    {
        $form = $this->createForm(PackageSetType::class, $packageSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $objectManager->persist($packageSet);
            $objectManager->flush();

            return $this->redirectToRoute('admin_packages');
        }

        return $this->render('admin/package_set.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/settings/packages/new", name="admin_new_package")
     */
    public function newPackageSetAction(Request $request, EntityManagerInterface $objectManager)
    {
        $packageSet = new PackageSet();

        return $this->renderPackageSetForm($request, $packageSet, $objectManager);
    }

    /**
     * @Route("/admin/settings/packages/{id}", name="admin_package")
     */
    public function packageSetAction($id, Request $request, EntityManagerInterface $objectManager)
    {
        $packageSet = $this->getDoctrine()->getRepository(PackageSet::class)->find($id);

        if (!$packageSet) {
            throw $this->createNotFoundException(sprintf('Package set #%d does not exist', $id));
        }

        return $this->renderPackageSetForm($request, $packageSet, $objectManager);
    }

    public function newOrderAction(
        Request $request,
        EntityManagerInterface $objectManager,
        OrderFactory $orderFactory,
        OrderNumberAssignerInterface $orderNumberAssigner
    )
    {
        $delivery = new Delivery();
        $form = $this->createForm(NewOrderType::class, $delivery);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $delivery = $form->getData();

            $variantName = $form->get('variantName')->getData();
            $variantPrice = $form->get('variantPrice')->getData();

            $order = $this->createOrderForDelivery($orderFactory, $delivery, $variantPrice);

            $variant = $order->getItems()->get(0)->getVariant();

            $variant->setName($variantName);
            $variant->setCode(Uuid::uuid4()->toString());

            $order->setState(OrderInterface::STATE_ACCEPTED);

            $objectManager->persist($order);
            $objectManager->flush();

            $orderNumberAssigner->assignNumber($order);

            $objectManager->flush();

            return $this->redirectToRoute('admin_order', [ 'id' => $order->getId() ]);
        }

        return $this->render('admin/new_order.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function taskReceiptAction($id)
    {
        $task = $this->getDoctrine()->getRepository(Task::class)->find($id);

        $html = $this->get('twig')->render('task/receipt.pdf.twig', [
            'task' => $task,
        ]);

        $pdf = $this->browserlessClient->request('POST', '/pdf', [
            'json' => ['html' => $html]
        ]);

        $response = new Response((string) $pdf->getContent());

        $response->headers->add(['Content-Type' => 'application/pdf']);
        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                sprintf('task-%d-receipt.pdf', $task->getId())
            )
        ]);

        return $response;
    }

    public function customizeAction(Request $request)
    {
        $isDemo = $this->getParameter('is_demo');

        if ($isDemo) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(CustomizeType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_customize');
        }

        return $this->render('admin/customize.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/organizations", name="admin_organizations")
     */
    public function organizationsAction()
    {
        $organizations = $this->getDoctrine()->getRepository(Organization::class)->findAll();

        return $this->render('admin/organizations.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * @Route("/admin/organizations/new", name="admin_add_organization")
     */
    public function addOrganizationAction(Request $request)
    {
        $form = $this->createForm(OrganizationType::class);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $organization = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($organization);
            $em->flush();

            return new RedirectResponse($this->generateUrl('admin_organizations'));
        }

        return $this->render('admin/add_organization.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/organizations/{id}/configure", name="admin_organization_configure")
     */
    public function configureOrganizationAction($id, Request $request)
    {
        $organization = $this->getDoctrine()->getRepository(Organization::class)->find($id);

        if (!$organization) {
            throw $this->createNotFoundException(sprintf('Organization #%d does not exist', $id));
        }

        $organizationConfig = $this->getDoctrine()->getRepository(OrganizationConfig::class)
            ->findOneBy(['organization' => $organization]);

        if (!$organizationConfig) {
            $organizationConfig = new OrganizationConfig($organization);
        }

        $form = $this->createForm(AddOrganizationType::class, $organizationConfig);
        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $organization = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($organization);
            $em->flush();

            return new RedirectResponse($this->generateUrl('admin_organizations'));
        }

        return $this->render(
            'admin/add_organization.html.twig',
            [
                'form' => $form->createView(),
                'organization' => $organization,
            ]
        );
    }

    private function handleHubForm(Hub $hub, Request $request)
    {
        $form = $this->createForm(HubType::class, $hub);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $this->getDoctrine()->getManager()->persist($hub);
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_hub', ['id' => $hub->getId()]);
        }

        return $this->render('admin/hub.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function newHubAction(Request $request)
    {
        $hub = new Hub();

        return $this->handleHubForm($hub, $request);
    }

    public function hubAction($id, Request $request)
    {
        $hub = $this->getDoctrine()->getRepository(Hub::class)->find($id);

        if (!$hub) {
            throw $this->createNotFoundException(sprintf('Hub #%d does not exist', $id));
        }

        return $this->handleHubForm($hub, $request);
    }

    public function hubsAction(Request $request)
    {
        $hubs = $this->getDoctrine()->getRepository(Hub::class)->findAll();

        return $this->render('admin/hubs.html.twig', [
            'hubs' => $hubs,
        ]);
    }

    /**
     * @HideSoftDeleted
     */
    public function restaurantListAction(Request $request, SettingsManager $settingsManager)
    {
        $routes = $request->attributes->get('routes');

        $pledgeCount = $this->getDoctrine()
            ->getRepository(Pledge::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.state = :state_new')
            ->setParameter('state_new', 'new')
            ->getQuery()
            ->getSingleScalarResult()
            ;

        $pledgesEnabled = filter_var(
            $settingsManager->get('enable_restaurant_pledges'),
            FILTER_VALIDATE_BOOLEAN
        );

        $pledgeForm = $this->createFormBuilder([
            'enable_restaurant_pledges' => $pledgesEnabled,
        ])
        ->add('enable_restaurant_pledges', CheckboxType::class, [
            'label' => 'form.settings.enable_restaurant_pledges.label',
            'required' => false,
        ])
        ->getForm();

        $pledgeForm->handleRequest($request);

        if ($pledgeForm->isSubmitted() && $pledgeForm->isValid()) {

            $enabled = $pledgeForm->get('enable_restaurant_pledges')->getData();

            $settingsManager->set('enable_restaurant_pledges', $enabled ? 'yes' : 'no');
            $settingsManager->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_restaurants');
        }

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
            'products_route' => $routes['products'],
            'pledge_count' => $pledgeCount,
            'pledge_form' => $pledgeForm->createView(),
            'nonprofits_enabled' => $this->getParameter('nonprofits_enabled')
        ]);
    }


    /**
     * @param Nonprofit $nonprofit
     * @param Request $request
     * @return RedirectResponse|Response
     */
    private function handleNonprofitForm(Nonprofit $nonprofit, Request $request)
    {
        $form = $this->createForm(NonprofitType::class, $nonprofit);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $this->getDoctrine()->getManager()->persist($nonprofit);
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_nonprofits');
        }

        return $this->render('admin/nonprofit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Handle POST request from nonprofit form
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function newNonprofitAction(Request $request)
    {
        $nonprofit = new Nonprofit();

        return $this->handleNonprofitForm($nonprofit, $request);
    }

    /**
     * Build and return the form of a specific nonprofit
     *
     * @param int $id
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function nonprofitAction(int $id, Request $request)
    {
        $nonprofit = $this->getDoctrine()->getRepository(Nonprofit::class)->find($id);

        if (!$nonprofit) {
            throw $this->createNotFoundException(sprintf('Nonprofit #%d does not exist', $id));
        }

        return $this->handleNonprofitForm($nonprofit, $request);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return RedirectResponse
     */
    public function deleteNonprofitAction(int $id, Request $request): RedirectResponse
    {
        $nonprofit = $this->getDoctrine()->getRepository(Nonprofit::class)->find($id);
        $this->entityManager->remove($nonprofit);
        $this->entityManager->flush();

        return $this->redirectToRoute('admin_nonprofits');
    }

    /**
     * Build the nonprofit list page
     *
     * @param Request $request
     * @return Response
     */
    public function nonProfitsActionListAction(
        Request $request
    ): Response
    {
        $nonprofits = $this->entityManager->getRepository(Nonprofit::class)->findAll();

        return $this->render('admin/nonprofits.html.twig', [
            'nonprofits' => $nonprofits
        ]);
    }

    public function metricsAction(
        LocalBusinessRepository $localBusinessRepository,
        CubeJsTokenFactory $tokenFactory,
        Request $request)
    {
        $zeroWasteCount = $localBusinessRepository->countZeroWaste();

        return $this->render('admin/metrics.html.twig', [
            'cube_token' => $tokenFactory->createToken(),
            'zero_waste' => $zeroWasteCount > 0,
        ]);
    }

    /**
     * @Route("/admin/vehicles/new", name="admin_new_vehicle")
     */
    public function newVehicleAction(Request $request)
    {
        $vehicle = new Vehicle();

        $form = $this->createForm(VehicleType::class, $vehicle);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $this->getDoctrine()->getManager()->persist($vehicle);
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_vehicles');
        }

        return $this->render('admin/vehicle.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/vehicles", name="admin_vehicles")
     */
    public function vehiclesAction()
    {
        $vehicles = $this->getDoctrine()->getRepository(Vehicle::class)->findAll();

        return $this->render('admin/vehicles.html.twig', [
            'vehicles' => $vehicles,
        ]);
    }
}
