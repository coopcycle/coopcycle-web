<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Invitation;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Entity\Sylius\PricingStrategy;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\AddUserType;
use AppBundle\Form\Order\NewOrderType;
use AppBundle\Form\Order\SubscriptionType;
use AppBundle\Form\StoreAddressesType;
use AppBundle\Form\StoreType;
use AppBundle\Form\AddressType;
use AppBundle\Form\DeliveryImportType;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\InvitationManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use Psr\Log\LoggerInterface;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

trait StoreTrait
{
    use InjectAuthTrait;

    /**
     * @HideSoftDeleted
     */
    public function storeListAction(Request $request, PaginatorInterface $paginator, JWTManagerInterface $jwtManager)
    {
        $qb = $this->getDoctrine()
        ->getRepository(Store::class)
        ->createQueryBuilder('c')
        ->orderBy('c.name', 'ASC');

        $STORES_PER_PAGE = 20;

        $stores = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            $STORES_PER_PAGE
        );

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), $this->auth([
            'stores' => $stores,
            'layout' => $request->attributes->get('layout'),
            'store_route' => $routes['store'],
            'store_delivery_new_route' => $routes['store_delivery_new'],
            'store_deliveries_route' => $routes['store_deliveries']
        ]));
    }

    public function storeUsersAction($id, Request $request,
        UserManagerInterface $userManager,
        InvitationManager $invitationManager)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        $addUserForm = $this->createForm(AddUserType::class);

        $routes = $request->attributes->get('routes');

        $addUserForm->handleRequest($request);
        if ($addUserForm->isSubmitted() && $addUserForm->isValid()) {

            $user = $addUserForm->get('user')->getData();

            // FIXME Association should be inversed
            $user->addStore($store);

            $userManager->updateUser($user);

            return $this->redirectToRoute('admin_store_users', ['id' => $id]);
        }

        $inviteForm = $this->createFormBuilder([])
            ->add('email', EmailType::class)
            ->getForm();

        $inviteForm->handleRequest($request);

        if ($inviteForm->isSubmitted() && $inviteForm->isValid()) {

            $email = $inviteForm->get('email')->getData();

            $invitation = new Invitation();
            $invitation->setUser($this->getUser());
            $invitation->setEmail($email);

            $invitation->addStore($store);
            $invitation->addRole('ROLE_STORE');

            $invitationManager->send($invitation);

            $this->addFlash(
                'notice',
                $this->translator->trans('basics.send_invitation.confirm')
            );

            return $this->redirectToRoute($routes['store_users'], ['id' => $id]);
        }

        return $this->render('store/users.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'users' => $store->getOwners(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'add_user_form' => $addUserForm->createView(),
            'invite_form' => $inviteForm->createView(),
            'store_addresses_route' => $routes['store_addresses']
        ]);
    }

    public function storeAddressAction($storeId, $addressId, Request $request, TranslatorInterface $translator)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($storeId);

        $this->accessControl($store, 'view');

        $address = $this->getDoctrine()->getRepository(Address::class)->find($addressId);

        if (!$store->getAddresses()->contains($address)) {
            throw new AccessDeniedHttpException('Access denied');
        }

        return $this->renderStoreAddressForm($store, $address, $request, $translator);
    }

    public function newStoreAddressAction($id, Request $request, TranslatorInterface $translator)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store, 'edit_delivery');

        $address = new Address();

        return $this->renderStoreAddressForm($store, $address, $request, $translator);
    }

    protected function renderStoreForm(Store $store, Request $request, TranslatorInterface $translator)
    {
        $form = $this->createForm(StoreType::class, $store);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->accessControl($store);

            /** @var Store $store */
            $store = $form->getData();
            $objectManager = $this->getDoctrine()->getManagerForClass(Store::class);

            if ($form->getClickedButton() && 'delete' === $form->getClickedButton()->getName()) {

                $this->getDoctrine()->getManagerForClass(Store::class)->remove($store);
                $this->getDoctrine()->getManagerForClass(Store::class)->flush();

                return $this->redirectToRoute($routes['stores']);
            }

            if ($store->isTransporterEnabled()) {
                $transporter = $store->getTransporter();
                $fstore = $objectManager->getRepository(Store::class)->findOneBy([
                    'transporter' => $transporter
                ]);

                if (!is_null($fstore) && $store->getId() != $fstore->getId()) {
                    $this->addFlash(
                        'error',
                        sprintf('%s is already enabled for %s', $transporter, $fstore->getName())
                    );

                    return $this->redirectToRoute($routes['store'], [ 'id' => $store->getId() ]);
                }

            }

            $objectManager->persist($store);
            $objectManager->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute($routes['store'], [ 'id' => $store->getId() ]);
        }

        return $this->render('store/form.html.twig', $this->auth([
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'store_route' => $routes['store'],
            'stores_route' => $routes['stores'],
            'store_delivery_new_route' => $routes['store_delivery_new'],
            'store_deliveries_route' => $routes['store_deliveries'],
            'store_addresses_route' => $routes['store_addresses'],
        ]));
    }

    protected function renderStoreAddressForm(Store $store, Address $address, Request $request, TranslatorInterface $translator)
    {
        $routes = $request->attributes->get('routes');

        $form = $this->createStoreAddressForm($address);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $address = $form->getData();

            $store->addAddress($address);

            // Set as default if no default address is defined yet
            if (null === $store->getAddress()) {
                $store->setAddress($address);
            }

            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute($routes['store_addresses'], ['id' => $store->getId()]);
        }

        return $this->render('store/address_form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'form' => $form->createView(),
        ]);
    }

    public function newStoreDeliveryAction($id, Request $request,
        DeliveryManager $deliveryManager,
        PricingManager $pricingManager,
        OrderManager $orderManager,
        OrderFactory $orderFactory,
        OrderNumberAssignerInterface $orderNumberAssigner,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
        $routes = $request->attributes->get('routes');

        $store = $entityManager
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store, 'edit_delivery');

        $delivery = null;
        $previousArbitraryPrice = null;

        if ($this->isGranted('ROLE_ADMIN')) {
            // pre-fill fields with the data from a previous order
            $data = $this->duplicateOrder($request, $store, $pricingManager);
            if (null !== $data) {
                $delivery = $data['delivery'];
                $previousArbitraryPrice = $data['previousArbitraryPrice'];
            }
        }

        if (null === $delivery) {
            $delivery = $store->createDelivery();
        }

        $form = $this->createForm(NewOrderType::class, $delivery, [
            'arbitrary_price' => $previousArbitraryPrice,
            'validation_groups' => ['Default', 'delivery_check']
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            if ($arbitraryPrice = $this->getArbitraryPrice($form)) {
                $this->createOrderForDeliveryWithArbitraryPrice($form, $orderFactory, $delivery,
                    $entityManager, $orderNumberAssigner);

                $order = $delivery->getOrder();
                $this->handleBookmark($orderManager, $form, $order);
                $this->handleNewSubscription($pricingManager, $logger, $store, $form, $delivery, $order, new UseArbitraryPrice($arbitraryPrice));

                $entityManager->flush();

                return $this->redirectToRoute($routes['success'], ['id' => $id]);

            } elseif ($store->getCreateOrders()) {

                try {

                    $price = $this->getDeliveryPrice($delivery, $store->getPricingRuleSet(), $deliveryManager);
                    $order = $this->createOrderForDelivery($orderFactory, $delivery, new PricingRulesBasedPrice($price), $this->getUser()->getCustomer());
                    $entityManager->persist($order);

                    $this->handleRememberAddress($store, $form);
                    $this->handleBookmark($orderManager, $form, $order);

                    $entityManager->flush();

                    // We need to persist the order before calling onDemand,
                    // because an auto increment is needed to generate a number
                    $orderManager->onDemand($order);

                    $this->handleNewSubscription($pricingManager, $logger, $store, $form, $delivery, $order);

                    $entityManager->flush();

                    return $this->redirectToRoute($routes['success'], ['id' => $id]);

                } catch (NoRuleMatchedException $e) {
                    $message = $translator->trans('delivery.price.error.priceCalculation', [], 'validators');
                    $form->addError(new FormError($message));
                }

            } else {

                $this->handleRememberAddress($store, $form);

                $entityManager->persist($delivery);
                $entityManager->flush();

                // TODO Add flash message

                return $this->redirectToRoute($routes['success'], ['id' => $id]);
            }
        }

        return $this->render('store/deliveries/new.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'back_route' => $routes['back'],
            'show_left_menu' => $request->attributes->get('show_left_menu', true),
        ]);
    }

    private function duplicateOrder(Request $request, Store $store, PricingManager $pricingManager)
    {
        $hashid = $request->query->get('frmrdr');

        if (null === $hashid) {
            return null;
        }

        $hashids = new Hashids($this->getParameter('secret'), 16);
        $decoded = $hashids->decode($hashid);
        if (count($decoded) !== 1) {
            return null;
        }

        $fromOrder = current($decoded);
        return $pricingManager->duplicateOrder($store, $fromOrder);
    }

    public function subscriptionAction($storeId, $subscriptionId,
        Request $request,
        PricingManager $pricingManager,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    )
    {
        $subscription = $entityManager
            ->getRepository(RecurrenceRule::class)
            ->find($subscriptionId);

        $store = $subscription->getStore();

        // Currently the route is only accessible by ROLE_DISPATCHER,
        // so this check is not doing much, but it would be useful
        // if we decide to open the route to store owners
        $this->denyAccessUnlessGranted('view', $store);

        $tempDelivery = $pricingManager->createDeliveryFromSubscription($subscription, Carbon::now()->format('Y-m-d'), false);

        $routes = $request->attributes->get('routes');

        $arbitraryPrice = null;
        if ($arbitraryPriceTemplate = $subscription->getArbitraryPriceTemplate()) {
            $arbitraryPrice = new ArbitraryPrice($arbitraryPriceTemplate['variantName'], $arbitraryPriceTemplate['variantPrice']);
        }

        $form = $this->createForm(SubscriptionType::class, $tempDelivery, [
            'arbitrary_price' => $arbitraryPrice,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $tempDelivery = $form->getData();

            $arbitraryPrice = $this->getArbitraryPrice($form);
            $rule = $this->getRecurrenceRule($form, $logger);

            if (null !== $rule) {
                $pricingManager->updateSubscription($subscription, $tempDelivery, $rule, $arbitraryPrice ? new UseArbitraryPrice($arbitraryPrice) : new UsePricingRules);
                $this->handleRememberAddress($store, $form);
                $entityManager->flush();
            } else {
                $pricingManager->cancelSubscription($subscription, $tempDelivery);
            }

            return $this->redirectToRoute($routes['success']);
        }

        return $this->render('store/subscriptions/item.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'subscription' => $subscription,
            'delivery' => $tempDelivery,
            'form' => $form->createView(),
        ]);
    }

    private function handleRememberAddress(Store $store, FormInterface $form)
    {
        foreach ($form->get('tasks') as $form) {
            $addressForm = $form->get('address');
            $rememberAddress = $addressForm->has('rememberAddress') && $addressForm->get('rememberAddress')->getData();
            $duplicateAddress = $addressForm->has('duplicateAddress') && $addressForm->get('duplicateAddress')->getData();
            // If the user has specified to duplicate address,
            // we *DON'T* add it to the address book
            if ($rememberAddress && !$duplicateAddress) {
                $task = $form->getData();
                $store->addAddress($task->getAddress());
            }
        }
    }

    private function handleBookmark(OrderManager $orderManager, FormInterface $form, OrderInterface $order): void
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if (!$form->has('bookmark')) {
            return;
        }

        $isBookmarked = true === $form->get('bookmark')->getData();
        $orderManager->setBookmark($order, $isBookmarked);
    }

    private function handleNewSubscription(
        PricingManager $pricingManager,
        LoggerInterface $logger,
        Store $store,
        FormInterface $form,
        Delivery $delivery,
        OrderInterface $order,
        PricingStrategy $pricingStrategy = new UsePricingRules): void
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $recurrenceRule = $this->getRecurrenceRule($form, $logger);

        if (null === $recurrenceRule) {
            return;
        }

        $subscription = $pricingManager->createSubscription($store, $delivery, $recurrenceRule, $pricingStrategy);

        if (null !== $subscription) {
            $order->setSubscription($subscription);

            foreach ($delivery->getTasks() as $task) {
                $task->setRecurrenceRule($subscription);
            }
        }
    }

    private function getArbitraryPrice(FormInterface $form): ?ArbitraryPrice
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if (!$form->has('arbitraryPrice')) {
            return null;
        }

        if (true !== $form->get('arbitraryPrice')->getData()) {
            return null;
        }

        $variantPrice = $form->get('variantPrice')->getData();
        $variantName = $form->get('variantName')->getData();

        return new ArbitraryPrice($variantName, $variantPrice);
    }


    private function getRecurrenceRule(FormInterface $form, LoggerInterface $logger): ?Rule
    {
        if (!$form->has('recurrence')) {
            return null;
        }

        $recurrenceData = $form->get('recurrence')->getData();

        if (null === $recurrenceData) {
            return null;
        }

        $recurrence = json_decode($recurrenceData, true);

        if (null === $recurrence) {
            return null;
        }

        $ruleStr = $recurrence['rule'];

        if (null === $ruleStr) {
            return null;
        }

        $rule = null;
        try {
            $rule = new Rule($ruleStr);
        } catch (InvalidRRule $e) {
            $logger->warning('Invalid recurrence rule', [
                'rule' => $ruleStr,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        return $rule;
    }

    public function storeAction($id, Request $request, TranslatorInterface $translator)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store, 'view');

        return $this->renderStoreForm($store, $request, $translator);
    }

    public function storeDeliveriesAction($id, Request $request, PaginatorInterface $paginator,
        DeliveryRepository $deliveryRepository,
        MessageBusInterface $messageBus,
        EntityManagerInterface $entityManager,
        Hashids $hashids8,
        Filesystem $deliveryImportsFilesystem,
        SlugifyInterface $slugify,
    )
    {
        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store, 'view');

        $routes = $request->attributes->get('routes');

        $deliveryImportForm = $this->createForm(DeliveryImportType::class);

        $deliveryImportForm->handleRequest($request);
        if ($deliveryImportForm->isSubmitted() && $deliveryImportForm->isValid()) {
            $this->accessControl($store, 'edit_delivery');

            return $this->handleDeliveryImportForStore(
                store: $store,
                form: $deliveryImportForm,
                routeTo: $routes['import_success'],
                messageBus: $messageBus,
                entityManager: $entityManager,
                hashids: $hashids8,
                filesystem: $deliveryImportsFilesystem,
                slugify: $slugify
            );
        }

        /** @var QueryBuilder $qb */
        $qb = $deliveryRepository->createQueryBuilderWithTasks();

        $filters = [
            'enabled' => false,
            'query' => null,
            'range' => [null, null],
        ];

        if ($request->query->get('q')) {

            $filters['enabled'] = true;
            $filters['query'] = $request->query->get('q');

            // Redirect startin with #
            if (str_starts_with($filters['query'], '#')) {
                $searchId = intval(trim($filters['query'], '#'));
                if (null !== $deliveryRepository->find($searchId)) {
                    return $this->redirectToRoute($this->getDeliveryRoutes()['view'], ['id' => $searchId]);
                }
            }

            $deliveryRepository->searchWithSonic($qb, $filters['query'], $request->getLocale(), $store);

        } else {
            $qb
                ->andWhere('d.store = :store')
                ->setParameter('store', $store);
        }

        //TODO: Remove duplicated code (AdminController.php~L820)
        if ($request->query->get('start_at') && $request->query->get('end_at')) {

            $start = Carbon::parse($request->query->get('start_at'))->setTime(0, 0, 0)->toDateTime();
            $end = Carbon::parse($request->query->get('end_at'))->setTime(23, 59, 59)->toDateTime();

            $filters['enabled'] = true;
            $filters['range'] = [$start, $end];

            $qb
                ->andWhere('d.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $deliveries = $paginator->paginate(
            $filters['enabled'] ? $qb : $deliveryRepository->past($qb),
            $request->query->getInt('page', 1),
            10,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 't.doneBefore',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
                PaginatorInterface::SORT_FIELD_ALLOW_LIST => ['t.doneBefore'],
                PaginatorInterface::FILTER_FIELD_ALLOW_LIST => []
            ]
        );

        return $this->render('store/deliveries.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'deliveries' => $deliveries,
            'filters' => $filters,
            'today' => $filters['enabled'] ?: $deliveryRepository->today($qb)->getQuery()->getResult(),
            'upcoming' => $filters['enabled'] ?: $deliveryRepository->upcoming($qb)->getQuery()->getResult(),
            'routes' => $this->getDeliveryRoutes(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'delivery_import_form' => $deliveryImportForm->createView(),
        ]);
    }

    public function storeDeliveriesBookmarksAction($id, Request $request,
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository)
    {
        /**
         * Currently we only support bookmarks for admin users,
         * if (when) we need to support bookmarks for store owners,
         * make sure that admin users and store owners can't see each other's bookmarks
         */

        $store = $entityManager
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store, 'view');

        $routes = $request->attributes->get('routes');

        return $this->render('store/deliveries_bookmarks.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'bookmarks' => $orderRepository->findBookmarked($store, $this->getUser()),
            'routes' => $this->getDeliveryRoutes(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
        ]);
    }

    public function storeAddressesAction($id, Request $request, TranslatorInterface $translator)
    {
        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store, 'view');

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(StoreAddressesType::class, $store);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $translator->trans('global.changesSaved')
            );

            return $this->redirectToRoute($routes['store_addresses'], [ 'id' => $store->getId() ]);
        }

        $address = new Address();

        $addressForm = $this->createStoreAddressForm($address);

        return $this->render('store/addresses.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'address_form' => $addressForm->createView(),
            'store_address_new_route' => $routes['store_address_new'],
            'store_address_route' => $routes['store_address'],
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'store_addresses_route' => $routes['store_addresses'],
        ]);
    }

    private function createStoreAddressForm(Address $address)
    {
        return $this->createForm(AddressType::class, $address, [
            'with_name' => true,
            'with_widget' => true,
            'with_telephone' => true,
            'with_contact_name' => true,
        ]);
    }

    public function downloadDeliveryImagesAction($storeId, $deliveryId, Request $request,
        StorageInterface $storage,
        Filesystem $taskImagesFilesystem)
    {
        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->find($deliveryId);

        $this->denyAccessUnlessGranted('edit', $delivery);

        if (!$delivery->hasImages()) {
            throw new BadRequestHttpException(sprintf('Delivery #%d has no images', $deliveryId));
        }

        $zip = new \ZipArchive();
        $zipName = tempnam(sys_get_temp_dir(), 'coopcycle_delivery_images');
        $zip->open($zipName, \ZipArchive::CREATE);

        foreach ($delivery->getImages() as $image) {

            // FIXME
            // It's not clean to use resolveUri()
            // but the problem is that resolvePath() returns the path with prefix,
            // while $taskImagesFilesystem is alreay aware of the prefix
            $imagePath = ltrim($storage->resolveUri($image, 'file'), '/');

            if (!$taskImagesFilesystem->fileExists($imagePath)) {
                throw new BadRequestHttpException(sprintf('Image at path "%s" not found', $imagePath));
            }

            $zip->addFromString(basename($imagePath), $taskImagesFilesystem->read($imagePath));
        }

        $zip->close();

        $response = new Response(file_get_contents($zipName));
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('coopcycle-delivery-images-%d.zip', $deliveryId)
        ));

        unlink($zipName);

        return $response;
    }

    protected function handleDeliveryImportForStore(
        Store $store,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        Hashids $hashids,
        Filesystem $filesystem,
        MessageBusInterface $messageBus,
        SlugifyInterface $slugify,
        string $routeTo)
    {   
        
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $form->get('file')->getData();

        $queue = new DeliveryImportQueue();
        $queue->setStore($store);

        $entityManager->persist($queue);
        $entityManager->flush();

        // slugify to get a safe filename. it may return an empty string but we had a hash afterwards so it should be alright.
        $filename = $slugify->slugify($uploadedFile->getClientOriginalName()); 
        $filename = substr($filename, 0, -4); // remove .csv extension in filename
        $filename = substr($filename, 0, 100); // limit the filename length

        $filename = sprintf(
            '%s_%s.%s',
            $filename,
            $hashids->encode($queue->getId()),
            $uploadedFile->guessExtension()
        );

        try {

            $filesystem->write($filename, $uploadedFile->getContent());

            $queue->setFilename($filename);
            $entityManager->flush();

            $messageBus->dispatch(
                new ImportDeliveries($filename),
                [ new DelayStamp(5000) ]
            );

            $this->addFlash(
                'notice',
                $this->translator->trans('deliveries.importing', ['%filename%' => $filename])
            );

        } catch (FilesystemException | UnableToWriteFile $e) {

            $entityManager->remove($queue);
            $entityManager->flush();

            $this->addFlash(
                'error',
                $this->translator->trans('deliveries.import_upload_error')
            );
        }

        return $this->redirectToRoute($routeTo, ['section' => 'imports']);
    }
}
