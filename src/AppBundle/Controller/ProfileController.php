<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Form\AddressType;
use AppBundle\Form\OrderType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\TaskCompleteType;
use AppBundle\Service\SocketIoManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\OrderEventCollection;
use Doctrine\Common\Collections\Collection;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Cocur\Slugify\SlugifyInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
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
            'product_new' => 'admin_restaurant_product_new',
            'dashboard' => 'profile_restaurant_dashboard',
            'planning' => 'profile_restaurant_planning',
            'stripe_oauth_redirect' => 'profile_restaurant_stripe_oauth_redirect',
            // 'preparation_time' => '',
            'stats' => 'profile_restaurant_stats',
            'deposit_refund' => 'profile_restaurant_deposit_refund',
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

    public function indexAction(Request $request, SlugifyInterface $slugify, TranslatorInterface $translator)
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

            return $this->storeDeliveriesAction($store->getId(), $request, $translator);

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

            return $this->statsAction($restaurant->getId(), $request, $slugify);
        }

        if ($user->hasRole('ROLE_COURIER')) {

            return $this->tasksAction($request);
        }

        return $this->render('@App/profile/index.html.twig', array(
            'user' => $user,
        ));
    }

    /**
     * @Route("/profile/edit", name="profile_edit")
     * @Template()
     */
    public function editProfileAction(Request $request) {

        $user = $this->getUser();

        $editForm = $this->createForm(UpdateProfileType::class, $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $userManager = $this->getDoctrine()->getManagerForClass(ApiUser::class);
            $userManager->persist($user);
            $userManager->flush();

            return $this->redirectToRoute('fos_user_profile_show');
        }

        return array(
            'form' => $editForm->createView()
        );
    }

    protected function getOrderList(Request $request)
    {
        $qb = $this->get('sylius.repository.order')
            ->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state != :state')
            ->setParameter('customer', $this->getUser())
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
            ->orderBy('o.shippedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return [ $orders, $pages, $page ];
    }

    public function orderAction($id, Request $request, OrderManager $orderManager, JWTManagerInterface $jwtManager)
    {
        $order = $this->container->get('sylius.repository.order')->find($id);

        if ($order->getCustomer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($order->isFoodtech()) {

            $reset = false;
            if ($request->query->has('reset')) {
                $reset = $request->query->getBoolean('reset');
            }

            $trackGoal = false;
            if ($request->getSession()->getFlashBag()->has('track_goal')) {
                $messages = $request->getSession()->getFlashBag()->get('track_goal');
                $trackGoal = !empty($messages);
            }

            $goalId = getenv('MATOMO_CHECKOUT_COMPLETED_GOAL_ID');
            if (!empty($goalId)) {
                $goalId = intval($goalId);
            }

            return $this->render('@App/order/foodtech.html.twig', [
                'layout' => '@App/profile.html.twig',
                'order' => $order,
                'events' => (new OrderEventCollection($order))->toArray(),
                'order_normalized' => $this->get('serializer')->normalize($order, 'jsonld', ['groups' => ['order'], 'is_web' => true]),
                'breadcrumb_path' => 'profile_orders',
                'reset' => $reset,
                'track_goal' => $trackGoal,
                'goal_id' => $goalId,
                'jwt' => $jwtManager->create($this->getUser()),
            ]);
        }

        $form = $this->createForm(OrderType::class, $order);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->getClickedButton()) {

                if ('cancel' === $form->getClickedButton()->getName()) {
                    $orderManager->cancel($order);
                }

                $this->get('sylius.manager.order')->flush();

                return $this->redirectToRoute('profile_orders');
            }
        }

        $pickupAddress = $order->getDelivery()->getPickup()->getAddress();
        $dropoffAddress = $order->getDelivery()->getDropoff()->getAddress();

        $pickupAt = $order->getDelivery()->getPickup()->getDoneBefore();
        $dropoffAt = $order->getDelivery()->getDropoff()->getDoneBefore();

        return $this->render('@App/order/service.html.twig', [
            'layout' => '@App/profile.html.twig',
            'order' => $order,
            'delivery' => $order->getDelivery(),
            'form' => $form->createView(),
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'pickup_at' => $pickupAt,
            'dropoff_at' => $dropoffAt,
        ]);
    }

    /**
     * @Route("/profile/addresses", name="profile_addresses")
     * @Template()
     */
    public function addressesAction(Request $request)
    {
        return array(
            'addresses' => $this->getUser()->getAddresses(),
        );
    }

    /**
     * @Route("/profile/addresses/new", name="profile_address_new")
     * @Template()
     */
    public function newAddressAction(Request $request)
    {
        $address = new Address();

        $form = $this->createForm(AddressType::class, $address, [
            'with_name' => true
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

        return array(
            'form' => $form->createView(),
        );
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
     * @Template("@App/user/tracking.html.twig")
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

        return $this->render('@App/profile/tasks.html.twig', [
            'date' => $date,
            'tasks' => $tasks,
        ]);
    }

    /**
     * @Route("/profile/tasks/{id}/complete", name="profile_task_complete")
     * @Template()
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

        return [
            'form' => $form->createView(),
        ];
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

            return new JsonResponse($this->get('serializer')->normalize($notifications, 'json'));
        }

        return $this->render('@App/profile/notifications.html.twig', [
            'notifications' => $notifications
        ]);
    }

    /**
     * @Route("/profile/notifications/unread", name="profile_notifications_unread")
     */
    public function unreadNotificationsAction(Request $request, SocketIoManager $socketIoManager)
    {
        return new JsonResponse((int) $socketIoManager->countNotifications($this->getUser()));
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
