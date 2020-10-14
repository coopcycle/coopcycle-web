<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Form\AddressType;
use AppBundle\Form\OrderType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\TaskCompleteType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\SocketIoManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\OrderEventCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Cocur\Slugify\SlugifyInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends Controller
{
    const ITEMS_PER_PAGE = 20;

    use AccessControlTrait;
    use DeliveryTrait;
    use OrderTrait;
    use RestaurantTrait;
    use StoreTrait;
    use UserTrait;

    protected function getRestaurantRoutes()
    {
        return [
            'restaurants' => 'profile_restaurants',
            'restaurant' => 'profile_restaurant',
            'menu_taxons' => 'profile_restaurant_menu_taxons',
            'menu_taxon' => 'profile_restaurant_menu_taxon',
            'products' => 'profile_restaurant_products',
            'product_options' => 'profile_restaurant_product_options',
            'product_new' => 'profile_restaurant_product_new',
            'dashboard' => 'profile_restaurant_dashboard',
            'planning' => 'profile_restaurant_planning',
            'stripe_oauth_redirect' => 'profile_restaurant_stripe_oauth_redirect',
            'preparation_time' => 'profile_restaurant_preparation_time',
            'stats' => 'profile_restaurant_stats',
            'deposit_refund' => 'profile_restaurant_deposit_refund',
            'promotions' => 'profile_restaurant_promotions',
            'promotion_new' => 'profile_restaurant_new_promotion',
            'promotion' => 'profile_restaurant_promotion',
            'product_option_preview' => 'profile_restaurant_product_option_preview',
            'reusable_packaging_new' => 'profile_restaurant_new_reusable_packaging',
        ];
    }

    private function handleSwitchRequest(Request $request, Collection $items, $queryKey, $sessionKey)
    {
        if ($request->query->has($queryKey)) {
            foreach ($items as $item) {
                if ($item->getId() === $request->query->getInt($queryKey)) {
                    $request->getSession()->set($sessionKey, $item->getId());

                    return $this->redirectToRoute('fos_user_profile_show');
                }
            }

            throw $this->createAccessDeniedException();
        }
    }

    public function indexAction(Request $request,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        PaginatorInterface $paginator)
    {
        $user = $this->getUser();

        if ($user->hasRole('ROLE_STORE') && $request->attributes->has('_store')) {

            if ($response = $this->handleSwitchRequest($request, $user->getStores(), 'store', '_store')) {

                return $response;
            }

            $store = $request->attributes->get('_store');

            $routes = $request->attributes->has('routes') ? $request->attributes->get('routes') : [];
            $routes['import_success'] = 'fos_user_profile_show';
            $routes['stores'] = 'fos_user_profile_show';
            $routes['store'] = 'profile_store';

            $request->attributes->set('routes', $routes);

            return $this->storeDeliveriesAction($store->getId(), $request, $translator, $paginator);

            // FIXME Forward doesn't copy request attributes
            // return $this->forward('AppBundle\Controller\ProfileController::storeDeliveriesAction', [
            //     'id'  => $store->getId(),
            // ]);
        }

        if ($user->hasRole('ROLE_RESTAURANT') && $request->attributes->has('_restaurant')) {

            if ($response = $this->handleSwitchRequest($request, $user->getRestaurants(), 'restaurant', '_restaurant')) {

                return $response;
            }

            $restaurant = $request->attributes->get('_restaurant');

            return $this->statsAction($restaurant->getId(), $request, $slugify, $translator);
        }

        if ($user->hasRole('ROLE_COURIER')) {

            return $this->tasksAction($request);
        }

        $customer = $user->getCustomer();

        $loopeatAuthorizeUrl = '';

        if ($this->getParameter('loopeat_enabled') && !$customer->hasLoopEatCredentials()) {

            $redirectUri = $this->generateUrl('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $redirectAfterUri = $this->generateUrl(
                'fos_user_profile_show',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Use a JWT as the "state" parameter
            $state = $jwtEncoder->encode([
                'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
                'sub' => $iriConverter->getIriFromItem($customer),
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

        return $this->render('profile/index.html.twig', array(
            'user' => $user,
            'customer' => $customer,
            'loopeat_authorize_url' => $loopeatAuthorizeUrl,
        ));
    }

    /**
     * @Route("/profile/edit", name="profile_edit")
     */
    public function editProfileAction(Request $request, UserManagerInterface $userManager) {

        $user = $this->getUser();

        $editForm = $this->createForm(UpdateProfileType::class, $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $userManager->updateUser($user);

            return $this->redirectToRoute('fos_user_profile_show');
        }

        return $this->render('profile/edit_profile.html.twig', array(
            'form' => $editForm->createView()
        ));
    }

    protected function getOrderList(Request $request)
    {
        $qb = $this->get('sylius.repository.order')
            ->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state != :state')
            ->setParameter('customer', $this->getUser()->getCustomer())
            ->setParameter('state', OrderInterface::STATE_CART);

        $count = (clone $qb)
            ->select('COUNT(o)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages  = ceil($count / self::ITEMS_PER_PAGE);
        $page   = $request->query->get('p', 1);
        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $orders = (clone $qb)
            ->setMaxResults(self::ITEMS_PER_PAGE)
            ->setFirstResult($offset)
            ->orderBy('LOWER(o.shippingTimeRange)', 'DESC')
            ->getQuery()
            ->getResult();

        return [ $orders, $pages, $page ];
    }

    public function orderAction($id, Request $request,
        OrderManager $orderManager,
        DeliveryManager $deliveryManager,
        JWTManagerInterface $jwtManager,
        JWSProviderInterface $jwsProvider,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $em)
    {
        $filter = $em->getFilters()->disable('enabled_filter');

        $order = $this->container->get('sylius.repository.order')->find($id);

        if ($order->getCustomer()->hasUser() && $order->getCustomer()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($order->isFoodtech()) {

            $exp = clone $order->getShippingTimeRange()->getUpper();
            $exp->modify('+3 hours');

            // FIXME We may generate expired tokens

            $jwt = $jwsProvider->create([
                // We add a custom "ord" claim to the token,
                // that will allow watching order events
                'ord' => $iriConverter->getIriFromItem($order),
                // Token expires 3 hours after expected completion
                'exp' => $exp->getTimestamp(),
            ])->getToken();

            return $this->render('profile/order.html.twig', [
                'order' => $order,
                'events' => (new OrderEventCollection($order))->toArray(),
                'order_normalized' => $this->get('serializer')->normalize($order, 'jsonld', [
                    'groups' => ['order'],
                    'is_web' => true
                ]),
                'reset' => false,
                'track_goal' => false,
                'jwt' => $jwt,
            ]);
        }

        $form = $this->createForm(OrderType::class, $order);

        // When the order is in state "new", it does not have a delivery
        $delivery = $order->getDelivery();
        if (null === $delivery) {
            $delivery = $deliveryManager->createFromOrder($order);
        }

        return $this->render('order/service.html.twig', [
            'layout' => 'profile.html.twig',
            'order' => $order,
            'delivery' => $delivery,
            'form' => $form->createView(),
            'show_buttons' => false,
        ]);
    }

    /**
     * @Route("/profile/addresses", name="profile_addresses")
     */
    public function addressesAction(Request $request)
    {
        return $this->render('profile/addresses.html.twig', array(
            'addresses' => $this->getUser()->getAddresses(),
        ));
    }

    /**
     * @Route("/profile/addresses/new", name="profile_address_new")
     */
    public function newAddressAction(Request $request)
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

            $manager = $this->getDoctrine()->getManagerForClass(Address::class);
            $manager->persist($address);
            $manager->flush();

            return $this->redirectToRoute('profile_addresses');
        }

        return $this->render('profile/new_address.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    protected function getRestaurantList(Request $request)
    {
        return [ $this->getUser()->getRestaurants(), 1, 1 ];
    }

    protected function getStoreList(Request $request)
    {
        return [ $this->getUser()->getStores(), 1, 1 ];
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'      => 'profile_tasks',
            'pick'      => 'profile_delivery_pick',
            'deliver'   => 'profile_delivery_deliver',
            'view'      => 'profile_delivery',
            'store_new' => 'profile_store_delivery_new'
        ];
    }

    /**
     * @Route("/profile/tracking/{date}", name="profile_tracking")
     */
    public function trackingAction($date, Request $request)
    {
        $date = new \DateTime($date);

        return $this->userTracking($this->getUser(), $date);
    }

    /**
     * @Route("/profile/tasks", name="profile_tasks")
     */
    public function tasksAction(Request $request)
    {
        $date = new \DateTime();
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }

        $taskList = $this->getDoctrine()
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

    /**
     * @Route("/profile/tasks/{id}/complete", name="profile_task_complete")
     */
    public function completeTaskAction($id, Request $request, TaskManager $taskManager)
    {
        $task = $this->getDoctrine()
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

                    $this->getDoctrine()
                        ->getManagerForClass(Task::class)
                        ->flush();

                } catch (\Exception $e) {
                    $this->addFlash(
                        'error',
                        $this->get('translator')->trans($e->getMessage())
                    );
                }
            }

            return $this->redirectToRoute('profile_tasks', ['date' => $task->getDoneBefore()->format('Y-m-d')]);
        }

        return $this->render('profile/complete_task.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/profile/jwt", methods={"GET"}, name="profile_jwt")
     */
    public function jwtAction(Request $request, JWTManagerInterface $jwtManager)
    {
        $user = $this->getUser();

        if ($request->getSession()->has('_jwt')) {

            $jwt = $request->getSession()->get('_jwt');

            try {
                $token = new PreAuthenticationJWTUserToken($jwt);
                $jwtManager->decode($token);
            } catch (JWTDecodeFailureException $e) {
                if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
                    $request->getSession()->set('_jwt', $jwtManager->create($user));
                }
            }

        } else {
            $request->getSession()->set('_jwt', $jwtManager->create($user));
        }

        return new JsonResponse($request->getSession()->get('_jwt'));
    }

    /**
     * @Route("/profile/notifications", name="profile_notifications")
     */
    public function notificationsAction(Request $request, SocketIoManager $socketIoManager)
    {
        $notifications = $socketIoManager->getLastNotifications($this->getUser());

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {

            return new JsonResponse([
                'notifications' => $this->get('serializer')->normalize($notifications, 'json'),
                'unread' => (int) $socketIoManager->countNotifications($this->getUser())
            ]);
        }

        return $this->render('profile/notifications.html.twig', [
            'notifications' => $notifications
        ]);
    }

    /**
     * @Route("/profile/notifications/read", methods={"POST"}, name="profile_notifications_mark_as_read")
     */
    public function markNotificationsAsReadAction(Request $request, SocketIoManager $socketIoManager)
    {
        $ids = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $ids = json_decode($content, true);
        }

        $socketIoManager->markAsRead($this->getUser(), $ids);

        return new Response('', 204);
    }
}
