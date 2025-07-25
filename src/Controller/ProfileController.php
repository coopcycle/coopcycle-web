<?php

namespace AppBundle\Controller;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Api\Dto\DeliveryMapper;
use AppBundle\Controller\Utils\InjectAuthTrait;
use AppBundle\Controller\Utils\LoopeatTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Form\AddressType;
use AppBundle\Form\BusinessAccountType;
use AppBundle\Form\OrderType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\TaskCompleteType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\TopBarNotifications;
use AppBundle\Service\OrderManager;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderEventCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Cocur\Slugify\SlugifyInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use phpcent\Client as CentrifugoClient;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class ProfileController extends AbstractController
{
    const ITEMS_PER_PAGE = 20;

    use OrderTrait;
    use UserTrait;
    use InjectAuthTrait;
    use LoopeatTrait;

    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected JWTTokenManagerInterface $JWTTokenManager,
        protected EntityManagerInterface $entityManager,
        protected NormalizerInterface $normalizer
    )
    { }

    public function indexAction(Request $request,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        PaginatorInterface $paginator,
        EntityManagerInterface $entityManager,
        EdenredAuthentication $edenredAuthentication)
    {
        $user = $this->getUser();

        $customer = $user->getCustomer();

        $loopeatAuthorizeUrl = '';

        if ($this->getParameter('loopeat_enabled') && !$customer->hasLoopEatCredentials()) {

            $redirectUri = $this->generateUrl('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $redirectAfterUri = $this->generateUrl(
                'profile_edit',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Use a JWT as the "state" parameter
            $state = $jwtEncoder->encode([
                'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
                'sub' => $iriConverter->getIriFromResource($customer),
                // The "iss" (Issuer) claim contains a redirect URL
                'iss' => $redirectAfterUri,
            ]);

            $queryString = http_build_query([
                'client_id' => $this->getParameter('loopeat_client_id'),
                'response_type' => 'code',
                'state' => $state,
                // FIXME redirect_uri doesn't work yet
                // 'redirect_uri' => $redirectUri,
            ]);

            $loopeatAuthorizeUrl = sprintf('%s/oauth/authorize?%s', $this->getParameter('loopeat_base_url'), $queryString);
        }

        $edenredAuthorizeUrl = '';
        if ($this->getParameter('edenred_enabled') && !$customer->hasEdenredCredentials()) {
            $edenredAuthorizeUrl = $edenredAuthentication->getAuthorizeUrl($customer);
        }

        return $this->render('profile/index.html.twig', array(
            'user' => $user,
            'customer' => $customer,
            'loopeat_authorize_url' => $loopeatAuthorizeUrl,
            'edenred_authorize_url' => $edenredAuthorizeUrl,
        ));
    }

    #[Route(path: '/profile/edit', name: 'profile_edit')]
    public function editProfileAction(Request $request, UserManagerInterface $userManager, TranslatorInterface $translator) {

        $user = $this->getUser();

        $editForm = $this->createForm(UpdateProfileType::class, $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            if ($editForm->getClickedButton() && 'loopeatDisconnect' === $editForm->getClickedButton()->getName()) {
                $user->getCustomer()->clearLoopEatCredentials();
            }

            if ($editForm->getClickedButton() && 'dabbaDisconnect' === $editForm->getClickedButton()->getName()) {
                $user->getCustomer()->clearDabbaCredentials();
            }

            $userManager->updateUser($user);

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );
        }

        return $this->render('profile/edit_profile.html.twig', $this->auth(array(
            'form' => $editForm->createView()
        )));
    }

    protected function getOrderList(Request $request, PaginatorInterface $paginator, $showCanceled = false)
    {
        Assert::isInstanceOf($this->orderRepository, EntityRepository::class);

        $qb = $this->orderRepository
            ->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state != :state')
            ->orderBy('LOWER(o.shippingTimeRange)', 'DESC')
            ->setParameter('customer', $this->getUser()->getCustomer())
            ->setParameter('state', OrderInterface::STATE_CART);

        return $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
            [
                PaginatorInterface::DISTINCT => false,
            ]
        );
    }

    public function orderAction($id, Request $request,
        OrderManager $orderManager,
        DeliveryManager $deliveryManager,
        DeliveryMapper $deliveryMapper,
        JWTTokenManagerInterface $jwtManager,
        JWSProviderInterface $jwsProvider,
        IriConverterInterface $iriConverter,
        NormalizerInterface $normalizer,
        CentrifugoClient $centrifugoClient)
    {
        /** @var OrderInterface|null */
        $order = $this->orderRepository->find($id);

        /** @var CustomerInterface */
        $customer = $order->getCustomer();

        if ($customer->hasUser() && $customer->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($order->hasVendor()) {

            // FIXME We may generate expired tokens

            $exp = clone $order->getShippingTimeRange()->getUpper();
            $exp->modify('+3 hours');

            return $this->render('profile/order.html.twig', $this->auth([
                'order' => $order,
                'orderAccessToken' => null, // not needed, as a guest user does not have access to this route
                'order_normalized' => $normalizer->normalize($order, 'jsonld', [
                    'groups' => ['order'],
                    'is_web' => true
                ]),
                'reset' => false,
                'track_goal' => false,
                'centrifugo' => [
                    'token'   => $centrifugoClient->generateConnectionToken($order->getId(), $exp->getTimestamp()),
                    'channel' => sprintf('%s_order_events#%d', $this->getParameter('centrifugo_namespace'), $order->getId())
                ]
            ]));
        }

        $form = $this->createForm(OrderType::class, $order);

        // When the order is in state "new", it does not have a delivery
        $delivery = $order->getDelivery();
        if (null === $delivery) {
            $delivery = $deliveryManager->createFromOrder($order);
        }

        $deliveryData = $deliveryMapper->map(
            $delivery,
            $order,
            null,
            false
        );

        return $this->render('order/item.html.twig', [
            'layout' => 'profile.html.twig',
            'order' => $order,
            'delivery' => $delivery,
            'deliveryData' => $deliveryData,
            'form' => $form->createView(),
            'show_buttons' => false,
        ]);
    }

    #[Route(path: '/profile/addresses', name: 'profile_addresses')]
    public function addressesAction(Request $request)
    {
        return $this->render('profile/addresses.html.twig', array(
            'addresses' => $this->getUser()->getAddresses(),
        ));
    }

    #[Route(path: '/profile/addresses/new', name: 'profile_address_new')]
    public function newAddressAction(Request $request, EntityManagerInterface $entityManager)
    {
        $address = new Address();

        $form = $this->createForm(AddressType::class, $address, [
            'with_name' => true,
            'with_widget' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address = $form->getData();

            $this->getUser()->addAddress($address);

            $entityManager->persist($address);
            $entityManager->flush();

            return $this->redirectToRoute('profile_addresses');
        }

        return $this->render('profile/new_address.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route(path: '/profile/tracking/{date}', name: 'profile_tracking')]
    public function trackingAction($date, Request $request)
    {
        $date = new \DateTime($date);

        return $this->userTracking($this->getUser(), $date);
    }

    #[Route(path: '/profile/tasks', name: 'profile_tasks')]
    public function tasksAction(Request $request, EntityManagerInterface $entityManager)
    {
        $date = new \DateTime();
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }

        $taskList = $entityManager
            ->getRepository(TaskList::class)
            ->findOneBy([
                'courier' => $this->getUser(),
                'date' => $date
            ]);

        $tasks = [];
        if ($taskList) {
            $tasks = $taskList->getTasks();
        }

        return $this->render('profile/tasks.html.twig', [
            'date' => $date,
            'tasks' => $tasks,
        ]);
    }

    #[Route(path: '/profile/tasks/{id}/complete', name: 'profile_task_complete')]
    public function completeTaskAction($id, Request $request, TaskManager $taskManager, TranslatorInterface $translator, EntityManagerInterface $entityManager)
    {
        $task = $entityManager
            ->getRepository(Task::class)
            ->find($id);

        $form = $this->createForm(TaskCompleteType::class, $task);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $task = $form->getData();
            $notes = $form->get('notes')->getData();

            if ($form->getClickedButton()) {

                try {

                    if ('done' === $form->getClickedButton()->getName()) {
                        $taskManager->markAsDone($task, $notes);
                    }
                    if ('fail' === $form->getClickedButton()->getName()) {
                        $taskManager->markAsFailed($task, $notes);
                    }

                    $entityManager->flush();

                } catch (\Exception $e) {
                    $this->addFlash(
                        'error',
                        $translator->trans($e->getMessage())
                    );
                }
            }

            return $this->redirectToRoute('profile_tasks', ['date' => $task->getDoneBefore()->format('Y-m-d')]);
        }

        return $this->render('profile/complete_task.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/profile/jwt', methods: ['GET'], name: 'profile_jwt')]
    public function jwtAction(Request $request,
        JWTTokenManagerInterface $jwtManager,
        CentrifugoClient $centrifugoClient)
    {
        $user = $this->getUser();

        if ($request->getSession()->has('_jwt')) {

            $jwt = $request->getSession()->get('_jwt');

            try {
                $token = new JWTPostAuthenticationToken($this->getUser(), 'web', [], $jwt);
                $jwtManager->decode($token);
            } catch (JWTDecodeFailureException $e) {
                if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
                    $request->getSession()->set('_jwt', $jwtManager->create($user));
                }
            }

        } else {
            $request->getSession()->set('_jwt', $jwtManager->create($user));
        }

        return new JsonResponse([
            'jwt' => $request->getSession()->get('_jwt'),
            'cent_ns'  => $this->getParameter('centrifugo_namespace'),
            'cent_usr' => $user->getUsername(),
            'cent_tok' => $centrifugoClient->generateConnectionToken($user->getUsername(), (time() + 3600)),
        ]);
    }

    #[Route(path: '/profile/notifications', name: 'profile_notifications')]
    public function notificationsAction(Request $request, TopBarNotifications $topBarNotifications, NormalizerInterface $normalizer)
    {
        $unread = (int) $topBarNotifications->countNotifications($this->getUser());

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {
            $notifications = $topBarNotifications->getNotifications($this->getUser());

            return new JsonResponse([
                'notifications' => $normalizer->normalize($notifications, 'json'),
                'unread' => $unread
            ]);
        }

        $page = $request->query->getInt('page', 1);

        $notifications = $topBarNotifications->getNotifications($this->getUser(), $page);

        return $this->render('profile/notifications.html.twig', [
            'notifications' => $notifications,
            'currentPage' => $page,
            'nextPage' => $page + 1,
            'hasNextPage' => ($unread / TopBarNotifications::NOTIFICATIONS_OFFSET) > $page,
        ]);
    }

    #[Route(path: '/profile/notifications/remove', methods: ['POST'], name: 'profile_notifications_remove')]
    public function removeNotificationsAction(Request $request, TopBarNotifications $topBarNotifications, NormalizerInterface $normalizer)
    {
        if ($request->query->has('all') && 'true' === $request->query->get('all')) {
            $topBarNotifications->markAllAsRead($this->getUser());
        } else {
            $ids = [];
            $content = $request->getContent();
            if (!empty($content)) {
                parse_str($content, $ids);
            }
            $topBarNotifications->markAsRead($this->getUser(), array_keys($ids));
        }

        return $this->notificationsAction($request, $topBarNotifications, $normalizer);
    }

    #[Route(path: '/profile/notification/{id}', methods: ['DELETE'], name: 'profile_notification_remove')]
    public function removeNotificationAction(Request $request, TopBarNotifications $topBarNotifications, NormalizerInterface $normalizer)
    {
        $topBarNotifications->markAsRead($this->getUser(), [$request->get('id')]);
        $unread = (int) $topBarNotifications->countNotifications($this->getUser());

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {
            $notifications = $topBarNotifications->getNotifications($this->getUser());

            return new JsonResponse([
                'notifications' => $normalizer->normalize($notifications, 'json'),
                'unread' => $unread
            ]);
        }

        /** @var int $page */
        $page = 1;
        $content = $request->getContent();
        if (!empty($content)) {
            $contentArray = [];
            parse_str($content, $contentArray);
            if (array_key_exists('page', $contentArray)) {
                $page = (int) $contentArray['page'];
            }
        }

        $notifications = $topBarNotifications->getNotifications($this->getUser(), $page);

        return $this->render('profile/notifications.html.twig', [
            'notifications' => $notifications,
            'currentPage' => $page,
            'nextPage' => $page + 1,
            'hasNextPage' => ($unread / TopBarNotifications::NOTIFICATIONS_OFFSET) > $page,
        ]);
    }

    #[Route(path: '/profile/notifications/read', methods: ['POST'], name: 'profile_notifications_mark_as_read')]
    public function markNotificationsAsReadAction(Request $request, TopBarNotifications $topBarNotifications)
    {
        $ids = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $ids = json_decode($content, true);
        }

        $topBarNotifications->markAsRead($this->getUser(), $ids);

        return new Response('', 204);
    }

    public function redirectToDashboardAction($path, Request $request, RouterInterface $router)
    {
        $dashboardPath = sprintf('/dashboard/%s', $path);

        try {

            $router->match($dashboardPath);

            $queryString = $request->getQueryString();

            return $this->redirect($dashboardPath . (!empty($queryString) ? sprintf('?%s', $queryString) : ''), 301);

        } catch (RoutingException $e) {}

        throw $this->createNotFoundException();
    }

    #[Route(path: '/profile/business-account', name: 'profile_business_account')]
    public function businessAccountAction(
        Request $request,
        EntityManagerInterface $objectManager,
        TranslatorInterface $translator)
    {
        $this->denyAccessUnlessGranted('ROLE_BUSINESS_ACCOUNT');

        $user = $this->getUser();

        $businessAccount = $user->getBusinessAccount();

        if (!$businessAccount) {
            throw $this->createNotFoundException('User does not have a business account associated');
        }

        $form = $this->createForm(BusinessAccountType::class, $businessAccount);
        $form->add('save', SubmitType::class, [
            'label'  => 'form.menu_editor.save.label',
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $objectManager->persist($businessAccount);
            $objectManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute('profile_business_account');
        }

        return $this->render('profile/business_account.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/profile/business-account-orders', name: 'profile_business_account_orders')]
    public function businessAccountOrdersAction(
        Request $request,
        EntityManagerInterface $objectManager,
        PaginatorInterface $paginator)
    {
        $this->denyAccessUnlessGranted('ROLE_BUSINESS_ACCOUNT');

        $user = $this->getUser();

        $businessAccount = $user->getBusinessAccount();

        if (!$businessAccount) {
            throw $this->createNotFoundException('User does not have a business account associated');
        }

        $orders = [];

        if (null !== $businessAccount->getId()) {
            Assert::isInstanceOf($this->orderRepository, EntityRepository::class);

            $qb = $this->orderRepository
                ->createQueryBuilder('o')
                ->andWhere('o.businessAccount = :business_account')
                ->andWhere('o.state != :state')
                ->orderBy('LOWER(o.shippingTimeRange)', 'DESC')
                ->setParameter('business_account', $businessAccount)
                ->setParameter('state', OrderInterface::STATE_CART);

            $orders = $paginator->paginate(
                $qb,
                $request->query->getInt('page', 1),
                self::ITEMS_PER_PAGE,
                [
                    PaginatorInterface::DISTINCT => false,
                ]
            );
        }

        return $this->render('profile/business_account_orders.html.twig', [
            'orders' => $orders,
            'routes' => [
                'restaurant' => 'restaurant',
                'order' => 'profile_order'
            ]
        ]);
    }
}
