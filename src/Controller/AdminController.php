<?php

namespace AppBundle\Controller;

use ACSEO\TypesenseBundle\Finder\CollectionFinderInterface;
use ACSEO\TypesenseBundle\Finder\TypesenseQuery;
use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\AdminDashboardTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\IncidentTrait;
use AppBundle\Controller\Utils\TransporterTrait;
use AppBundle\Controller\Utils\InjectAuthTrait;
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
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Hub;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\BusinessAccountInvitation;
use AppBundle\Entity\Invitation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\BusinessRestaurantGroup;
use AppBundle\Entity\Contract;
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
use AppBundle\Form\AttachToOrganizationType;
use AppBundle\Form\ApiAppType;
use AppBundle\Form\BannerType;
use AppBundle\Form\CustomizeType;
use AppBundle\Form\DataExportType;
use AppBundle\Form\DeliveryImportType;
use AppBundle\Form\EmbedSettingsType;
use AppBundle\Form\GeoJSONUploadType;
use AppBundle\Form\HubType;
use AppBundle\Form\FailureReasonSetType;
use AppBundle\Form\BusinessAccountType;
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
use AppBundle\Form\BusinessRestaurantGroupType;
use AppBundle\Form\SettingsType;
use AppBundle\Form\StripeLivemodeType;
use AppBundle\Form\Type\TimeSlotChoiceType;
use AppBundle\Form\Sylius\Promotion\CreditNoteType;
use AppBundle\Form\TimeSlotType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\UsersExportType;
use AppBundle\Form\VehicleType;
use AppBundle\Form\ZoneCollectionType;
use AppBundle\Serializer\ApplicationsNormalizer;
use AppBundle\Service\ActivityManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\EmailManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\PackageSetManager;
use AppBundle\Service\PricingRuleSetManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TagManager;
use AppBundle\Service\TimeSlotManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\TokenGenerator as TokenGeneratorInterface;
use Nucleos\UserBundle\Util\Canonicalizer as CanonicalizerInterface;
use Nucleos\ProfileBundle\Mailer\Mail\RegistrationMail;
use Knp\Component\Pager\PaginatorInterface;
use Ramsey\Uuid\Uuid;
use Redis;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Twig\Environment as TwigEnvironment;
use phpcent\Client as CentrifugoClient;


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
    use IncidentTrait;
    use TransporterTrait;
    use InjectAuthTrait;

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
            'image_from_url' => 'admin_restaurant_image_from_url',
        ];
    }

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        protected EntityManagerInterface $entityManager,
        PromotionCouponRepositoryInterface $promotionCouponRepository,
        FactoryInterface $promotionRuleFactory,
        FactoryInterface $promotionFactory,
        HttpClientInterface $browserlessClient,
        UploaderHelper $uploaderHelper,
        bool $optinExportUsersEnabled,
        CollectionFinderInterface $typesenseShopsFinder,
        bool $adhocOrderEnabled,
        protected Filesystem $incidentImagesFilesystem,
        protected Filesystem $edifactFilesystem,
        protected PricingRuleSetManager $pricingRuleSetManager,
        protected JWTTokenManagerInterface $JWTTokenManager,
        protected TimeSlotManager $timeSlotManager
    )
    {
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->promotionCouponRepository = $promotionCouponRepository;
        $this->promotionRuleFactory = $promotionRuleFactory;
        $this->promotionFactory = $promotionFactory;
        $this->browserlessClient = $browserlessClient;
        $this->uploaderHelper = $uploaderHelper;
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

    protected function getOrderList(Request $request, PaginatorInterface $paginator, $showCanceled = false)
    {
        if ($request->query->has('q')) {
            $qb = $this->orderRepository->search($request->query->get('q'));
        } else {
            $qb = $this->orderRepository
                ->createQueryBuilder('o');
            $qb
                ->andWhere('o.state != :state')
                ->setParameter('state', OrderInterface::STATE_CART)
                ->orderBy('LOWER(o.shippingTimeRange)', 'DESC')
                ->setFirstResult(($request->query->getInt('p', 1) - 1) * self::ITEMS_PER_PAGE)
                ->setMaxResults(self::ITEMS_PER_PAGE)
                ;
        }

        if (!$showCanceled) {
            $qb
                ->andWhere('o.state != :state_cancelled')
                ->setParameter('state_cancelled', OrderInterface::STATE_CANCELLED);
        }

        return $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
            [
                PaginatorInterface::DISTINCT => false,
            ]
        );
    }

    /**
     * @Route("/admin/orders/search", name="admin_orders_search")
     */
    public function searchOrdersAction(
        Request $request,
        OrderRepository $orderRepository
    )
    {
        $qb = $orderRepository->search($request->query->get('q'));

        $qb->setMaxResults(10);

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $order) {

            if (null !== $order->getCustomer()) {
                $name = sprintf(
                    '%s (%s)',
                    $order->getNumber(),
                    $order->getCustomer()->getEmailCanonical()
                );
            } else {
                $name = $order->getNumber();
            }

            $data[] = [
                'id' => $order->getId(),
                'name' => $name,
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
                // https://github.com/symfony/symfony/issues/35277
                /** @var \Symfony\Component\Form\Form $paymentForm */
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
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new RedirectResponse($this->generateUrl('admin_users_invite'));
        }
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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

        $countOrders = $this->orderRepository->createQueryBuilder('o');
        $countOrders->select('COUNT(o)');
        $countOrders->andWhere('o.customer = :customer');
        $countOrders->andWhere('o.state != :state');

        $lastOrder = $this->orderRepository->createQueryBuilder('o');
        $lastOrder->andWhere('o.customer = :customer');
        $lastOrder->andWhere('o.state != :state');
        $lastOrder->orderBy('o.updatedAt', 'DESC');
        $lastOrder->setMaxResults(1);

        foreach ($customers as $customer) {
            $key = $customer->getEmailCanonical();

            $countOrders->setParameter('customer', $customer);
            $countOrders->setParameter('state', OrderInterface::STATE_CART);

            $attributes[$key]['orders_count'] = $countOrders->getQuery()->getSingleScalarResult();

            $lastOrder->setParameter('customer', $customer);
            $lastOrder->setParameter('state', OrderInterface::STATE_CART);

            $attributes[$key]['last_order'] = $lastOrder->getQuery()->getOneOrNullResult();
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

            $restaurants = [];
            $stores = [];
            if ($form->has('restaurants')) {
                $restaurants = $form->get('restaurants')->getData();
            }
            if ($form->has('stores')) {
                $stores = $form->get('stores')->getData();
            }

            // Prevent non admin user to invite users as admin
            if (!$this->isGranted('ROLE_ADMIN')) {
                $roles = array_diff($roles, ['ROLE_ADMIN']);
            }
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
        $this->accessControl($user, 'view');

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
        $this->accessControl($user);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        // Roles that can be edited by admin
        $editableRoles = ['ROLE_ADMIN', 'ROLE_COURIER', 'ROLE_RESTAURANT', 'ROLE_STORE', 'ROLE_DISPATCHER'];

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
        $this->accessControl($user, 'delete');

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
            'name' => 'ASC',
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
        OrderManager $orderManager,
        DeliveryRepository $deliveryRepository,
        Hashids $hashids8,
        Filesystem $deliveryImportsFilesystem,
        MessageBusInterface $messageBus,
        CentrifugoClient $centrifugoClient
    )
    {
        $deliveryImportForm = $this->createForm(DeliveryImportType::class, null, [
            'with_store' => true
        ]);

        $deliveryImportForm->handleRequest($request);
        if ($deliveryImportForm->isSubmitted() && $deliveryImportForm->isValid()) {

            $store = $deliveryImportForm->get('store')->getData();

            return $this->handleDeliveryImportForStore(
                store: $store,
                form: $deliveryImportForm,
                messageBus: $messageBus,
                entityManager: $this->entityManager,
                filesystem: $deliveryImportsFilesystem,
                hashids: $hashids8,
                routeTo: 'admin_deliveries'
            );
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

        /** @var QueryBuilder $qb */
        $qb = $deliveryRepository->createQueryBuilderWithTasks();

        $filters = [
            'query' => null,
            'range' => null,
        ];

        if ($request->query->get('q')) {

            $filters['query'] = $request->query->get('q');

            // Redirect startin with #
            if (str_starts_with($filters['query'], '#')) {
                $searchId = intval(trim($filters['query'], '#'));
                if (null !== $deliveryRepository->find($searchId)) {
                    return $this->redirectToRoute($this->getDeliveryRoutes()['view'], ['id' => $searchId]);
                }
            }

            $deliveryRepository->searchWithSonic($qb, $filters['query'], $request->getLocale());

        } else {
            if ($request->query->has('section') && method_exists($deliveryRepository, $request->query->get('section'))) {
                $qb = call_user_func([ $deliveryRepository, $request->query->get('section') ], $qb);
            } else {
                $qb = $deliveryRepository->today($qb);
            }
        }

        if ($request->query->has('start_at') && $request->query->has('end_at')) {
            $start = Carbon::parse($request->query->get('start_at'))->setTime(0, 0, 0)->toDateTime();
            $end = Carbon::parse($request->query->get('end_at'))->setTime(23, 59, 59)->toDateTime();
            $filters['range'] = [$start, $end];

            $qb->andWhere('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);
        }

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

        $this->getDoctrine()->getManager()->getFilters()->enable('soft_deleteable');

        $stores = $this->getDoctrine()->getRepository(Store::class)->findBy([], ['name' => 'ASC']);

        $this->getDoctrine()->getManager()->getFilters()->disable('soft_deleteable');

        $importDate = new \DateTime($request->query->get('date', 'now'));

        $importQueues = $this->entityManager->getRepository(DeliveryImportQueue::class)
            ->createQueryBuilder('diq')
            ->andWhere('DATE(diq.createdAt) = :import_date')
            ->orderBy('diq.createdAt', 'DESC')
            ->setParameter('import_date', $importDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        return $this->render('admin/deliveries.html.twig', $this->auth([
            'deliveries' => $deliveries,
            'filters' => $filters,
            'routes' => $this->getDeliveryRoutes(),
            'stores' => $stores,
            'delivery_import_form' => $deliveryImportForm->createView(),
            'delivery_export_form' => $dataExportForm->createView(),
            'import_queues' => $importQueues,
            'import_date' => $importDate,
            'centrifugo_token' => $centrifugoClient->generateConnectionToken($this->getUser()->getUsername(), (time() + 3600)),
            'centrifugo_channel' => sprintf('%s_events#%s', $this->getParameter('centrifugo_namespace'), $this->getUser()->getUsername()),
        ]));
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
     * @Route("/admin/tags", name="admin_tags")
     */
    public function tagsAction(Request $request, TagManager $tagManager)
    {
        if ($request->isMethod('POST') && $request->request->has('delete')) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $id = $request->request->get('tag');
            $tag = $this->entityManager->getRepository(Tag::class)->find($id);
            $tagManager->untagAll($tag);
            $this->entityManager->remove($tag);
            $this->entityManager->flush();

            return  $this->redirectToRoute('admin_tags');
        }

        if ($request->query->has('format')) {
            if ('json' === $request->query->get('format')) {

                return new JsonResponse($tagManager->getAllTags());
            }
        }

        $tags = $this->entityManager->getRepository(Tag::class)->findBy(array(), array('name' => 'ASC'));

        return $this->render('admin/tags.html.twig', [
            'tags' => $tags
        ]);
    }

    /**
     * @Route("/admin/deliveries/pricing", name="admin_deliveries_pricing")
     */
    public function pricingRuleSetsAction(Request $request, PaginatorInterface $paginator, PricingRuleSetManager $pricingRuleSetManager, ApplicationsNormalizer $normalizer)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $qb = $this->getDoctrine()->getRepository(Delivery\PricingRuleSet::class)
            ->createQueryBuilder('rs')
            ->orderBy('rs.name', 'ASC')
            ->setFirstResult(max(($request->query->getInt('page', 1) - 1), 0) * self::ITEMS_PER_PAGE / 2)
            ->setMaxResults(self::ITEMS_PER_PAGE / 2);

        $paginatedRuleSets = $paginator->paginate(
            $qb,
            max($request->query->getInt('page', 1), 1),
            self::ITEMS_PER_PAGE / 2,
            [PaginatorInterface::DISTINCT => false]
        );

        $relatedEntitiesByPricingRuleSetId = [];

        // the way we get the results for applications of pricing rule set is not very efficient (3 requests per rule set), so let's not load too much of them
        // if needed optimize (and complexifiy !) the query to get applications of the pricing rule set
        array_map(
            function ($ruleSet) use (&$relatedEntitiesByPricingRuleSetId, $normalizer, $pricingRuleSetManager) {
                $normalizedRelatedEntities = array_map(
                    function ($entity) use ($normalizer) { return $normalizer->normalize($entity);},
                    $pricingRuleSetManager->getPricingRuleSetApplications($ruleSet)
                );
                $relatedEntitiesByPricingRuleSetId[$ruleSet->getId()] = $normalizedRelatedEntities;
            },
            $qb->getQuery()->getResult()
        );

        return $this->render(
            'admin/pricing_rule_sets.html.twig',
            $this->auth([
                'ruleSets' => $paginatedRuleSets,
                'relatedEntitiesByPricingRuleSetId' => $relatedEntitiesByPricingRuleSetId
            ])
        );
    }

    private function renderPricingRuleSetForm(Delivery\PricingRuleSet $ruleSet, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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

        return $this->render(
            'admin/pricing_rule_set.html.twig',
            $this->auth([
                'form' => $form->createView(),
                'packages' => $packageNames,
                'ruleSetId' => $ruleSet->getId()
        ]));
    }

    /**
     * @Route("/admin/deliveries/pricing/new", name="admin_deliveries_pricing_ruleset_new")
     */
    public function newPricingRuleSetAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $ruleSet = new Delivery\PricingRuleSet();

        return $this->renderPricingRuleSetForm($ruleSet, $request);
    }

    /**
     * @Route("/admin/deliveries/pricing/{id}", name="admin_deliveries_pricing_ruleset")
     */
    public function pricingRuleSetAction($id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $ruleSet = $this->getDoctrine()
            ->getRepository(Delivery\PricingRuleSet::class)
            ->find($id);

        $duplicated = $ruleSet->duplicate($this->translator);

        return $this->renderPricingRuleSetForm($duplicated, $request);
    }

    private function renderFailureReasonSetForm(Delivery\FailureReasonSet $failureReasonSet, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $originalReasons = new ArrayCollection();

        foreach ($failureReasonSet->getReasons() as $reason) {
            $originalReasons->add($reason);
        }

        $form = $this->createForm(FailureReasonSetType::class, $failureReasonSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $failureReasonSet = $form->getData();

            $em = $this->getDoctrine()->getManagerForClass(Delivery\PricingRule::class);

            foreach ($originalReasons as $originalReason) {
                if (!$failureReasonSet->getReasons()->contains($originalReason)) {
                    $em->remove($originalReason);
                }
            }

            foreach ($failureReasonSet->getReasons() as $reason) {
                $reason->setFailureReasonSet($failureReasonSet);
            }

            if (null === $failureReasonSet->getId()) {
                $em->persist($failureReasonSet);
            }

            $em->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_deliveries_failures_failurereasonset', ['id' => $failureReasonSet->getId()]);
        }

        return $this->render('admin/failure_reason_set.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/deliveries/failures", name="admin_failures_list")
     */
    public function failuresAction()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $failureReasonSets = $this->getDoctrine()
            ->getRepository(Delivery\FailureReasonSet::class)
            ->findBy(array(), array('name' => 'ASC'));

        return $this->render('admin/failures.html.twig', [
            'failureReasonSets' => $failureReasonSets
        ]);
    }

    /**
     * @Route("/admin/deliveries/failures/new", name="admin_deliveries_failures_failurereasonset_new")
     */
    public function newFailureReasonSetAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $failureReasonSet = new Delivery\FailureReasonSet();

        return $this->renderFailureReasonSetForm($failureReasonSet, $request);
    }

    /**
     * @Route("/admin/deliveries/failures/{id}", name="admin_deliveries_failures_failurereasonset")
     */
    public function failureReasonSetAction($id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $failureReasonSet = $this->getDoctrine()
            ->getRepository(Delivery\FailureReasonSet::class)
            ->find($id);

        return $this->renderFailureReasonSetForm($failureReasonSet, $request);
    }

    /**
     * @Route("/admin/deliveries/failures/{id}/delete", methods={"POST"}, name="admin_failures_delete")
     */
    public function deleteFailureReasonSetAction($id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $failureReasonSet = $this->getDoctrine()->getRepository(Delivery\FailureReasonSet::class)->find($id);

        $em = $this->getDoctrine()->getManagerForClass(Delivery\FailureReason::class);

        try {
            $em->remove($failureReasonSet);
            $em->flush();
            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );
        } catch (ForeignKeyConstraintViolationException $_) {
            $this->addFlash(
                'error',
                $this->translator->trans('adminDashboard.failureSet.cant_delete_failure_reason_used')
            );
        }

        return $this->redirectToRoute('admin_failures_list');
    }

    /**
     * @Route("/admin/zones/{id}/delete", methods={"POST"}, name="admin_zone_delete")
     */
    public function deleteZoneAction($id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
     * @Route("/admin/restaurant/{restaurantId}/menus", name="admin_restaurant_menus")
     */
    public function searchRestaurantMenusAction($restaurantId)
    {
        $restaurant = $this->getDoctrine()->getRepository(LocalBusiness::class)->find($restaurantId);

        $data = [];
        foreach($restaurant->getTaxons() as $taxon) {
            $data[] = [
                'id' => $taxon->getId(),
                'name' => $taxon->getName()
            ];
        }

        return new JsonResponse($data);
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $deliveryForm = new DeliveryForm();

        $form = $this->createForm(EmbedSettingsType::class, $deliveryForm);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // Disable "Show on Home Page" on all forms ONLY if this new form is setted true
            if($deliveryForm->getShowHomepage()){
                $forms = $this->getDoctrine()->getRepository(DeliveryForm::class)->findAll();
                foreach ($forms as $formTemp) {
                    $formTemp->setShowHomepage(false);
                }
            }

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $deliveryForm = $this->getDoctrine()->getRepository(DeliveryForm::class)->find($id);

        $form = $this->createForm(EmbedSettingsType::class, $deliveryForm);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // Disable "Show on Home Page" on all forms except current form if setted true
            if($deliveryForm->getShowHomepage()){
                $forms = $this->getDoctrine()->getRepository(DeliveryForm::class)->findAll();
                foreach ($forms as $formTemp) {
                    if($deliveryForm->getId() != $formTemp->getId()){ //except current form
                        $formTemp->setShowHomepage(false);
                    }
                }
            }

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
        $forms = $this->getDoctrine()->getRepository(DeliveryForm::class)->findBy(array(), array('id' => 'ASC'));
        return $this->render('admin/forms.html.twig', $this->auth(['forms' => $forms]));
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($request->isMethod('POST') && $request->request->has('oauth2_client')) {

            $oAuth2ClientId = $request->get('oauth2_client');
            $oAuth2Client = $this->entityManager
                ->getRepository(OAuth2Client::class)
                ->find($oAuth2ClientId);

            $newSecret = hash('sha512', random_bytes(32));
            $oAuth2Client->setSecret($newSecret); /* @phpstan-ignore method.notFound */

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/integrations.html.twig');
    }

    /**
     * @Route("/admin/integrations/woopit", name="admin_integrations_woopit")
     */
    public function integrationWoopitAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST') && $request->request->has('oauth2_client')) {

            $oAuth2ClientId = $request->get('oauth2_client');
            $oAuth2Client = $this->entityManager
                ->getRepository(OAuth2Client::class)
                ->find($oAuth2ClientId);

            $newSecret = hash('sha512', random_bytes(32));
            $oAuth2Client->setSecret($newSecret); /* @phpstan-ignore method.notFound */

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
    public function timeSlotsAction(Request $request, PaginatorInterface $paginator, ApplicationsNormalizer $normalizer, TimeSlotManager $timeSlotManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $qb = $this->entityManager->getRepository(TimeSlot::class)
            ->createQueryBuilder('rs')
            ->orderBy('rs.name', 'ASC')
            ->setFirstResult(max(($request->query->getInt('page', 1) - 1), 0) * self::ITEMS_PER_PAGE / 2)
            ->setMaxResults(self::ITEMS_PER_PAGE / 2);

        $paginatedTimeSlots = $paginator->paginate(
            $qb,
            max($request->query->getInt('page', 1), 1),
            self::ITEMS_PER_PAGE / 2,
            [PaginatorInterface::DISTINCT => false]
        );

        $relatedEntitiesByTimeSlotId = [];

        // if needed optimize (and complexifiy !) the query to get applications of the time slot
        array_map(
            function ($ruleSet) use (&$relatedEntitiesByTimeSlotId, $normalizer, $timeSlotManager) {
                $normalizedRelatedEntities = array_map(
                    function ($entity) use ($normalizer) { return $normalizer->normalize($entity);},
                    $timeSlotManager->getTimeSlotApplications($ruleSet)
                );
                $relatedEntitiesByTimeSlotId[$ruleSet->getId()] = $normalizedRelatedEntities;
            },
            $qb->getQuery()->getResult()
        );

        return $this->render('admin/time_slots.html.twig', $this->auth([
            'time_slots' => $paginatedTimeSlots,
            'relatedEntitiesByTimeSlotId' => $relatedEntitiesByTimeSlotId
        ]));
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $timeSlot = $this->getDoctrine()->getRepository(TimeSlot::class)->find($id);

        if (!$timeSlot) {
            throw $this->createNotFoundException(sprintf('Time slot #%d does not exist', $id));
        }

        return $this->renderTimeSlotForm($request, $timeSlot, $objectManager);
    }

    /**
     * @Route("/admin/settings/packages", name="admin_packages")
     */
    public function packageSetsAction(Request $request, PaginatorInterface $paginator, PackageSetManager $packageSetManager, ApplicationsNormalizer $normalizer)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $qb = $this->getDoctrine()->getRepository(PackageSet::class)
            ->createQueryBuilder('ps')
            ->orderBy('ps.name', 'ASC')
            ->setFirstResult(($request->query->getInt('page', 1) - 1) * self::ITEMS_PER_PAGE / 2)
            ->setMaxResults(self::ITEMS_PER_PAGE / 2);

            $packageSets = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE / 2,
            [PaginatorInterface::DISTINCT => false,]
        );

        $relatedEntitiesByPackageSetId = [];
        // the way we get the results for applications of package set is not very efficient (3 requests per rule set), so let's not load too much of them
        // if needed optimize (and complexifiy !) the query to get applications of the package set
        array_map(
            function ($packageSet) use (&$relatedEntitiesByPackageSetId, $normalizer, $packageSetManager) {
                $normalizedRelatedEntities = array_map(
                    function ($entity) use ($normalizer) { return $normalizer->normalize($entity);},
                    $packageSetManager->getPackageSetApplications($packageSet)
                );
                $relatedEntitiesByPackageSetId[$packageSet->getId()] = $normalizedRelatedEntities;
            },
            $qb->getQuery()->getResult()
        );

        return $this->render(
            'admin/package_sets.html.twig',
            $this->auth(['package_sets' => $packageSets, 'relatedEntitiesByPackageSetId' => $relatedEntitiesByPackageSetId])
        );
    }

    private function renderPackageSetForm(Request $request, PackageSet $packageSet, EntityManagerInterface $objectManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(PackageSetType::class, $packageSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $objectManager->persist($packageSet);
            $objectManager->flush();

            return $this->redirectToRoute('admin_packages');
        }

        return $this->render('admin/package_set.html.twig', $this->auth(['form' => $form->createView()]));
    }

    /**
     * @Route("/admin/settings/packages/new", name="admin_new_package")
     */
    public function newPackageSetAction(Request $request, EntityManagerInterface $objectManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $packageSet = new PackageSet();

        return $this->renderPackageSetForm($request, $packageSet, $objectManager);
    }

    /**
     * @Route("/admin/settings/packages/{id}", name="admin_package")
     */
    public function packageSetAction($id, Request $request, EntityManagerInterface $objectManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

    private function handleBusinessRestaurantGroupForm(BusinessRestaurantGroup $businessRestaurantGroup, Request $request)
    {
        $originalRestaurantsWithMenu = new ArrayCollection();

        foreach($businessRestaurantGroup->getRestaurantsWithMenu() as $restaurantMenu) {
            $originalRestaurantsWithMenu->add($restaurantMenu);
        }

        $form = $this->createForm(BusinessRestaurantGroupType::class, $businessRestaurantGroup);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            foreach ($originalRestaurantsWithMenu as $restaurantMenu) {
                if (false === $businessRestaurantGroup->getRestaurantsWithMenu()->contains($restaurantMenu)) {
                    $businessRestaurantGroup->removeRestaurantWithMenu($restaurantMenu);
                    $this->entityManager->remove($restaurantMenu);
                }
            }

            $this->entityManager->persist($businessRestaurantGroup);
            $this->entityManager->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_business_restaurant_group', ['id' => $businessRestaurantGroup->getId()]);
        }

        return $this->render('admin/business_restaurant_group.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function newHubAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $hub = new Hub();

        return $this->handleHubForm($hub, $request);
    }

    public function newBusinessRestaurantGroupAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $businessRestaurantGroup = new BusinessRestaurantGroup();

        return $this->handleBusinessRestaurantGroupForm($businessRestaurantGroup, $request);
    }

    public function hubAction($id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $hub = $this->getDoctrine()->getRepository(Hub::class)->find($id);

        if (!$hub) {
            throw $this->createNotFoundException(sprintf('Hub #%d does not exist', $id));
        }

        return $this->handleHubForm($hub, $request);
    }

    public function businessRestaurantGroupAction($id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $businessRestaurantGroup = $this->getDoctrine()->getRepository(BusinessRestaurantGroup::class)->find($id);

        if (!$businessRestaurantGroup) {
            throw $this->createNotFoundException(sprintf('Restaurants For Business #%d does not exist', $id));
        }

        return $this->handleBusinessRestaurantGroupForm($businessRestaurantGroup, $request);
    }

    public function businessRestaurantGroupListAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $businessRestaurantGroupList = $this->getDoctrine()->getRepository(BusinessRestaurantGroup::class)->findAll();

        return $this->render('admin/business_restaurant_group_list.html.twig', [
            'business_restaurant_group_list' => $businessRestaurantGroupList,
        ]);
    }

    public function hubsAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $hubs = $this->getDoctrine()->getRepository(Hub::class)->findAll();

        return $this->render('admin/hubs.html.twig', [
            'hubs' => $hubs,
        ]);
    }

    public function businessAccountsAction()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $accounts = $this->getDoctrine()->getRepository(BusinessAccount::class)->findBy(array(), array('name' => 'ASC'));

        return $this->render('admin/business_accounts.html.twig', [
            'accounts' => $accounts,
        ]);
    }

    public function newBusinessAccountAction(
        Request $request,
        CanonicalizerInterface $canonicalizer,
        EmailManager $emailManager,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $objectManager,
        PaginatorInterface $paginator)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $account = new BusinessAccount();

        return $this->handleBusinessAccountForm($account, $request, $canonicalizer, $emailManager, $tokenGenerator, $objectManager, $paginator);
    }

    public function businessAccountResendRegistrationEmailAction(
        Request $request, EmailManager $emailManager, EntityManagerInterface $objectManager)
    {
        if ($request->request->has('invitationId')) {
            $invitationId = $request->request->get('invitationId');

            $businessAccountInvitation = $objectManager->getRepository(BusinessAccountInvitation::class)->find($invitationId);
            $businessAccount = $businessAccountInvitation->getBusinessAccount();
            $invitation = $businessAccountInvitation->getInvitation();

            $message = $emailManager->createBusinessAccountInvitationMessage($invitation, $businessAccount);
            $emailManager->sendTo($message, $invitation->getEmail());
            $invitation->setSentAt(new \DateTime());

            $objectManager->persist($invitation);
            $objectManager->flush();

            $this->addFlash(
                'notice',
                $this->translator->trans('form.business_acount.resend_invitation.confirm')
            );

            return $this->redirectToRoute('admin_business_account', ['id' => $businessAccount->getId()]);
        }

        $this->addFlash(
            'notice',
            $this->translator->trans('form.business_acount.resend_invitation.failed')
        );

        return $this->redirectToRoute('admin_business_accounts');
    }

    private function handleBusinessAccountForm(
        BusinessAccount $businessAccount,
        Request $request,
        CanonicalizerInterface $canonicalizer,
        EmailManager $emailManager,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $objectManager,
        PaginatorInterface $paginator)
    {
        $form = $this->createForm(BusinessAccountType::class, $businessAccount);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            if (null === $businessAccount->getId()) {
                $managerEmail = $form->get('managerEmail')->getData();

                $invitation = new Invitation();
                $invitation->setEmail($canonicalizer->canonicalize($managerEmail));
                $invitation->setUser($this->getUser());
                $invitation->setCode($tokenGenerator->generateToken());
                $invitation->addRole('ROLE_BUSINESS_ACCOUNT');

                // Send invitation email
                $message = $emailManager->createBusinessAccountInvitationMessage($invitation, $businessAccount);
                $emailManager->sendTo($message, $invitation->getEmail());
                $invitation->setSentAt(new \DateTime());

                $businessAccountInvitation = new BusinessAccountInvitation();
                $businessAccountInvitation->setBusinessAccount($businessAccount);
                $businessAccountInvitation->setInvitation($invitation);

                $objectManager->persist($businessAccountInvitation);

                $this->addFlash(
                    'notice',
                    $this->translator->trans('form.business_acount.send_invitation.confirm')
                );
            } else {
                $this->addFlash(
                    'notice',
                    $this->translator->trans('global.changesSaved')
                );
            }

            $objectManager->persist($businessAccount);
            $objectManager->flush();

            return $this->redirectToRoute('admin_business_accounts');
        }

        $orders = [];

        if (null !== $businessAccount->getId()) {
            $qb = $objectManager->getRepository(Order::class)->createQueryBuilder('o');
            $qb
                ->andWhere('o.businessAccount = :business_account')
                ->setParameter('business_account', $businessAccount);

            $orders = $paginator->paginate(
                $qb,
                $request->query->getInt('page', 1),
                self::ITEMS_PER_PAGE,
                [
                    PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 'o.createdAt',
                    PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
                    PaginatorInterface::SORT_FIELD_ALLOW_LIST => ['o.createdAt'],
                ]
            );
        }

        return $this->render('admin/business_account.html.twig', [
            'form' => $form->createView(),
            'orders' => $orders,
        ]);
    }

    public function businessAccountAction(
        $id,
        Request $request,
        CanonicalizerInterface $canonicalizer,
        EmailManager $emailManager,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $objectManager,
        PaginatorInterface $paginator)
    {
        if ($this->isGranted('ROLE_BUSINESS_ACCOUNT')) {
            $businessAccount = $this->getUser()->getBusinessAccount();
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $businessAccount = $this->getDoctrine()->getRepository(BusinessAccount::class)->find($id);
        }

        if (!$businessAccount) {
            throw $this->createNotFoundException(sprintf('Business account #%d does not exist', $id));
        }

        return $this->handleBusinessAccountForm($businessAccount, $request, $canonicalizer, $emailManager, $tokenGenerator, $objectManager, $paginator);
    }


    /**
     * @HideSoftDeleted
     */
    public function restaurantListAction(Request $request, SettingsManager $settingsManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

        return $this->render($request->attributes->get('template'), $this->auth([
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
            'nonprofits_enabled' => $this->getParameter('nonprofits_enabled'),
        ]));
    }


    /**
     * @param Nonprofit $nonprofit
     * @param Request $request
     * @return RedirectResponse|Response
     */
    private function handleNonprofitForm(Nonprofit $nonprofit, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $vehicles = $this->getDoctrine()->getRepository(Vehicle::class)->findAll();

        return $this->render('admin/vehicles.html.twig', [
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * @Route("/admin/cube", name="admin_cube")
     */
    public function cubeAction(CubeJsTokenFactory $tokenFactory)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/cube.html.twig', [
            'cube_token' => $tokenFactory->createToken(),
        ]);
    }

}
