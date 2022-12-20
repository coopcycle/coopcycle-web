<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Form\AddressType;
use AppBundle\Form\OrderType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\TaskCompleteType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\TopBarNotifications;
use AppBundle\Service\OrderManager;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\OrderEventCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use League\Csv\Writer as CsvWriter;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Cocur\Slugify\SlugifyInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use phpcent\Client as CentrifugoClient;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends AbstractController
{
    const ITEMS_PER_PAGE = 20;

    use OrderTrait;
    use UserTrait;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

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

        if ($user->hasRole('ROLE_COURIER')) {

            return $this->tasksAction($request);
        }

        $customer = $user->getCustomer();

        $loopeatAuthorizeUrl = '';

        if ($this->getParameter('loopeat_enabled') && !$customer->hasLoopEatCredentials()) {

            $redirectUri = $this->generateUrl('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $redirectAfterUri = $this->generateUrl(
                'nucleos_profile_profile_show',
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

    /**
     * @Route("/profile/edit", name="profile_edit")
     */
    public function editProfileAction(Request $request, UserManagerInterface $userManager) {

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

            return $this->redirectToRoute('nucleos_profile_profile_show');
        }

        return $this->render('profile/edit_profile.html.twig', array(
            'form' => $editForm->createView()
        ));
    }

    protected function getOrderList(Request $request, $showCanceled = false)
    {
        $qb = $this->orderRepository
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
        $page   = $request->query->getInt('p', 1);
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
        NormalizerInterface $normalizer,
        CentrifugoClient $centrifugoClient)
    {
        $order = $this->orderRepository->find($id);

        $customer = $order->getCustomer();

        if ($customer->hasUser() && $customer->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($order->hasVendor()) {

            // FIXME We may generate expired tokens

            $exp = clone $order->getShippingTimeRange()->getUpper();
            $exp->modify('+3 hours');

            return $this->render('profile/order.html.twig', [
                'order' => $order,
                'events' => (new OrderEventCollection($order))->toArray(),
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
    public function completeTaskAction($id, Request $request, TaskManager $taskManager, TranslatorInterface $translator)
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

    /**
     * @Route("/profile/jwt", methods={"GET"}, name="profile_jwt")
     */
    public function jwtAction(Request $request,
        JWTManagerInterface $jwtManager,
        CentrifugoClient $centrifugoClient)
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

        return new JsonResponse([
            'jwt' => $request->getSession()->get('_jwt'),
            'cent_ns'  => $this->getParameter('centrifugo_namespace'),
            'cent_usr' => $user->getUsername(),
            'cent_tok' => $centrifugoClient->generateConnectionToken($user->getUsername(), (time() + 3600)),
        ]);
    }

    /**
     * @Route("/profile/notifications", name="profile_notifications")
     */
    public function notificationsAction(Request $request, TopBarNotifications $topBarNotifications, NormalizerInterface $normalizer)
    {
        $notifications = $topBarNotifications->getLastNotifications($this->getUser());

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {

            return new JsonResponse([
                'notifications' => $normalizer->normalize($notifications, 'json'),
                'unread' => (int) $topBarNotifications->countNotifications($this->getUser())
            ]);
        }

        return $this->render('profile/notifications.html.twig', [
            'notifications' => $notifications
        ]);
    }

    /**
     * @Route("/profile/notifications/read", methods={"POST"}, name="profile_notifications_mark_as_read")
     */
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

    /**
     * @Route("/profile/loopeat", name="profile_loopeat")
     */
    public function loopeatAction(Request $request, CubeJsTokenFactory $tokenFactory, HttpClientInterface $cubejsClient)
    {
        $this->denyAccessUnlessGranted('ROLE_LOOPEAT');

        $cubeJsToken = $tokenFactory->createToken();

        if ($request->isMethod('POST')) {

            $response = $cubejsClient->request('POST', 'load', [
                'headers' => [
                    'Authorization' => $cubeJsToken,
                    'Content-Type' => 'application/json',
                ],
                'body' => '{"query":{"measures":[],"timeDimensions":[],"order":[["Loopeat.orderDate","desc"]],"dimensions":["Loopeat.restaurantName","Loopeat.orderNumber","Loopeat.orderDate","Loopeat.customerEmail","Loopeat.grabbedQuantity","Loopeat.returnedQuantity"]}}'
            ]);

            // Need to invoke a method on the Response,
            // to actually throw the Exception here
            // https://github.com/symfony/symfony/issues/34281
            // https://symfony.com/doc/5.4/http_client.html#handling-exceptions
            $content = $response->getContent();

            $resultSet = json_decode($content, true);

            $csv = CsvWriter::createFromString('');
            $csv->insertOne(array_keys($resultSet['data'][0]));
            $csv->insertAll($resultSet['data']);

            $response = new Response($csv->getContent());
            $response->headers->add(['Content-Type' => 'text/csv']);
            $response->headers->add([
                'Content-Disposition' => $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'loopeat.csv'
                )
            ]);

            return $response;
        }

        return $this->render('profile/loopeat.html.twig', [
            'cube_token' => $cubeJsToken,
        ]);
    }
}
