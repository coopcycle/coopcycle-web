<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\LocalBusinessTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Order;
use AppBundle\Entity\Store;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Form\AddressType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\TaskCompleteType;
use Doctrine\ORM\Query\Expr;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route as Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class ProfileController extends Controller
{
    const ITEMS_PER_PAGE = 20;

    use AccessControlTrait;
    use DeliveryTrait;
    use LocalBusinessTrait;
    use OrderTrait;
    use RestaurantTrait;
    use StoreTrait;
    use UserTrait;

    /**
     * @Route("/profile/edit", name="profile_edit")
     * @Template()
     *
     * @param Request $request
     * @return array
     */
    public function editProfile(Request $request) {

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
        $orderRepository = $this->getDoctrine()->getRepository(Order::class);

        $page = $request->query->get('page', 1);

        $qb = $orderRepository->createQueryBuilder('o');

        $qb->select($qb->expr()->count('o'))
           ->where('o.customer = ?1')
           ->setParameter(1, $this->getUser());

        $query = $qb->getQuery();
        $ordersCount = $query->getSingleScalarResult();

        $perPage = 15;

        $pages = ceil($ordersCount / $perPage);
        $offset = $perPage * ($page - 1);

        $orders = $orderRepository->findBy(
            ['customer' => $this->getUser()],
            ['createdAt' => 'DESC'],
            $perPage,
            $offset
        );

        return [ $orders, $pages, $page ];
    }

    /**
     * @Template("@App/Order/details.html.twig")
     */
    public function orderAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository(Order::class)->find($id);

        $orderEvents = [];
        foreach ($order->getEvents() as $event) {
            $orderEvents[] = [
                'status' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        $deliveryEvents = [];
        foreach ($order->getDelivery()->getEvents() as $event) {
            $deliveryEvents[] = [
                'status' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        return array(
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld'),
            'order_events_json' => $this->get('serializer')->serialize($orderEvents, 'json'),
            'delivery_events_json' => $this->get('serializer')->serialize($deliveryEvents, 'json'),
            'layout' => 'AppBundle::profile.html.twig',
            'breadcrumb_path' => 'profile_orders'
        );
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

        $form = $this->createForm(AddressType::class, $address);

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

    /**
     * @Route("/profile/payment", name="profile_payment")
     * @Template()
     */
    public function paymentAction(Request $request)
    {
        $stripeParams = $this->getUser()->getStripeParams();

        $stripeClientId = $this->getParameter('stripe_connect_client_id');
        $stripeAuthorizeURL = 'https://connect.stripe.com/oauth/authorize?response_type=code&client_id='.$stripeClientId.'&scope=read_write';

        return [
            'stripe_authorize_url' => $stripeAuthorizeURL,
            'stripe_user_id' => $stripeParams ? $stripeParams->getUserId() : null
        ];
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'    => 'profile_tasks',
            'pick'    => 'profile_delivery_pick',
            'deliver' => 'profile_delivery_deliver'
        ];
    }

    /**
     * @Route("/profile/tracking", name="profile_tracking")
     * @Template("@App/User/tracking.html.twig")
     */
    public function trackingAction(Request $request)
    {
        return $this->userTracking($this->getUser());
    }

    /**
     * @Route("/profile/tasks", name="profile_tasks")
     * @Template
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

        return [
            'date' => $date,
            'tasks' => $tasks,
        ];
    }

    public function editDeliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->find($id);

        return $this->renderDeliveryForm($delivery, $request, null, ['with_stores' => true]);
    }

    /**
     * @Route("/profile/tasks/{id}/complete", name="profile_task_complete")
     * @Template()
     */
    public function completeTaskAction($id, Request $request)
    {
        $taskManager = $this->get('coopcycle.task_manager');

        $task = $this->getDoctrine()
            ->getRepository(Task::class)
            ->find($id);

        $form = $this->createForm(TaskCompleteType::class, $task);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $task = $form->getData();
            $notes = $form->get('notes')->getData();

            if ($form->getClickedButton()) {
                if ('done' === $form->getClickedButton()->getName()) {
                    $taskManager->markAsDone($task);
                }
                if ('fail' === $form->getClickedButton()->getName()) {
                    $taskManager->markAsFailed($task, $notes);
                }
            }

            return $this->redirectToRoute('profile_tasks', ['date' => $task->getDoneBefore()->format('Y-m-d')]);
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
