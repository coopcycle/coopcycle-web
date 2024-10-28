<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Controller\Utils\InjectAuthTrait;
use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Controller\Utils\SelectPaymentMethodTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Embed\Context as EmbedContext;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderInvitation;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Form\Checkout\CheckoutAddressType;
use AppBundle\Form\Checkout\CheckoutCouponType;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Form\Checkout\CheckoutTipType;
use AppBundle\Form\Checkout\CheckoutVytalType;
use AppBundle\Form\Checkout\LoopeatReturnsType;
use AppBundle\Form\Checkout\CheckoutPayment;
use AppBundle\Form\Order\CartType;
use AppBundle\Security\OrderAccessTokenManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Service\TimingRegistry;
use AppBundle\Sylius\Cart\SessionStorage as CartStorage;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use AppBundle\Utils\OrderEventCollection;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\ShippingAddress as ShippingAddressConstraint;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCheckFileExistence;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use phpcent\Client as CentrifugoClient;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;

class OrderController extends AbstractController
{
    use OrderConfirmTrait;
    use UserTrait;
    use InjectAuthTrait;
    use SelectPaymentMethodTrait;

    public function __construct(
        private EntityManagerInterface $objectManager,
        private OrderFactory $orderFactory,
        protected JWTTokenManagerInterface $JWTTokenManager,
        private TimingRegistry $timingRegistry,
        private ValidatorInterface $validator,
        private OrderAccessTokenManager $orderAccessTokenManager,
        private LoggerInterface $checkoutLogger,
        private string $environment
    )
    {
    }

    /**
     * @Route("/order/", name="order")
     */
    public function indexAction(Request $request,
        OrderManager $orderManager,
        CartContextInterface $cartContext,
        OrderProcessorInterface $orderProcessor,
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        EmbedContext $embedContext,
        SessionInterface $session)
    {
        if (!$settingsManager->get('guest_checkout_enabled')) {
            if (!$embedContext->isEnabled()) {
                $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
            }
        }

        $order = $cartContext->getCart();

        if (null === $order || !$order->hasVendor()) {

            return $this->redirectToRoute('homepage');
        }

        $orderErrors = $this->validator->validate($order);

        // @see https://github.com/coopcycle/coopcycle-web/issues/2069
        if (count($orderErrors->findByCodes(ShippingAddressConstraint::ADDRESS_NOT_SET)) > 0) {

            $vendor = $order->getVendor();
            $routeName = $order->isMultiVendor() ? 'hub' : 'restaurant';

            return $this->redirectToRoute($routeName, ['id' => $vendor->getId()]);
        }

        $user = $this->getUser();

        // If the user is authenticated, use the corresponding customer
        // @see AppBundle\EventListener\WebAuthenticationListener
        if (null !== $user && $user->getCustomer() !== $order->getCustomer()) {

            $order->setCustomer($user->getCustomer());

            // Make sure to move Dabba credentials if any
            $dabbaAccessTokenKey =
                sprintf('dabba.order.%d.access_token', $order->getId());
            $dabbaRefreshTokenKey =
                sprintf('dabba.order.%d.refresh_token', $order->getId());

            if ($session->has($dabbaAccessTokenKey) && $session->has($dabbaRefreshTokenKey)) {
                $order->getCustomer()->setDabbaAccessToken(
                    $session->get($dabbaAccessTokenKey)
                );
                $order->getCustomer()->setDabbaRefreshToken(
                    $session->get($dabbaRefreshTokenKey)
                );
            }

            $this->objectManager->flush();

            if ($session->has($dabbaAccessTokenKey) && $session->has($dabbaRefreshTokenKey)) {
                $session->remove($dabbaAccessTokenKey);
                $session->remove($dabbaRefreshTokenKey);
            }
        }

        $originalPromotionCoupon = $order->getPromotionCoupon();
        $wasReusablePackagingEnabled = $order->isReusablePackagingEnabled();
        $originalReusablePackagingPledgeReturn = $order->getReusablePackagingPledgeReturn();

        $tipForm = $this->createForm(CheckoutTipType::class);
        $tipForm->handleRequest($request);

        if ($tipForm->isSubmitted() && $tipForm->isValid()) {

            $tipAmount = $tipForm->get('amount')->getData();
            $order->setTipAmount((int) ($tipAmount * 100));

            $orderProcessor->process($order);
            $this->objectManager->flush();

            return $this->redirectToRoute('order');
        }

        $couponForm = $this->createForm(CheckoutCouponType::class, $order);
        $couponForm->handleRequest($request);

        if ($couponForm->isSubmitted()) {

            $promotionCouponWasAdded =
                null === $originalPromotionCoupon && null !== $order->getPromotionCoupon();

            if ($promotionCouponWasAdded) {
                $this->addFlash(
                    'notice',
                    $translator->trans('promotions.promotion_coupon.success', [
                        '%code%' => $order->getPromotionCoupon()->getCode()
                    ])
                );
            } else {
                $this->addFlash(
                    'error',
                    'No coupon applied'
                );
            }

            $orderProcessor->process($order);
            $this->objectManager->flush();

            return $this->redirectToRoute('order');
        }

        $vytalForm = $this->createForm(CheckoutVytalType::class);
        $vytalForm->handleRequest($request);

        if ($vytalForm->isSubmitted()) {

            $vytalCode = $vytalForm->get('code')->getData();

            $order->setReusablePackagingEnabled(true);
            $order->setVytalCode($vytalCode);

            $orderProcessor->process($order);
            $this->objectManager->flush();

            return $this->redirectToRoute('order');
        }

        $loopeatReturnsForm = $this->createForm(LoopeatReturnsType::class, $order);
        $loopeatReturnsForm->handleRequest($request);

        if ($loopeatReturnsForm->isSubmitted()) {

            $returns = $loopeatReturnsForm->get('returns')->getData();

            $order->setLoopeatReturns(json_decode($returns, true));

            $orderProcessor->process($order);
            $this->objectManager->flush();

            return $this->redirectToRoute('order');
        }

        $form = $this->createForm(CheckoutAddressType::class, $order, [
            'csrf_protection' => 'test' !== $this->environment #FIXME; normally cypress e2e tests should run with CSRF protection enabled, but once in a while it fails
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            $order = $form->getData();

            if ($form->get('customer')->isValid()) {
                $customer = $form->get('customer')->getData();
                $order->setCustomer($customer);
            }

            $reusablePackagingWasChanged =
                $wasReusablePackagingEnabled !== $order->isReusablePackagingEnabled();

            $reusablePackagingPledgeReturnWasChanged =
                $originalReusablePackagingPledgeReturn !== $order->getReusablePackagingPledgeReturn();

            // In those cases, we always reload the page
            if ($reusablePackagingWasChanged || $reusablePackagingPledgeReturnWasChanged) {

                // Make sure to reset return counter
                if (!$order->isReusablePackagingEnabled()) {
                    $order->setReusablePackagingPledgeReturn(0);
                    $order->setLoopeatReturns([]);
                }

                $orderProcessor->process($order);
                $this->objectManager->flush();

                return $this->redirectToRoute('order');
            }

            if ($orderErrors->count() === 0 && $form->isValid()) {

                // https://github.com/coopcycle/coopcycle-web/issues/1910
                // Maybe a better would be to use "empty_data" option in CheckoutAddressType
                if (null !== $originalPromotionCoupon && null === $order->getPromotionCoupon()) {
                    $order->setPromotionCoupon($originalPromotionCoupon);
                }

                $orderProcessor->process($order);

                $isQuote = $form->getClickedButton() && 'quote' === $form->getClickedButton()->getName();
                $isFreeOrder = $order->isFree();

                if ($isQuote) {
                    $orderManager->quote($order);
                } elseif ($isFreeOrder) {
                    $orderManager->checkout($order);
                }

                $this->objectManager->flush();

                if ($isFreeOrder || $isQuote) {

                    return $this->redirectToOrderConfirm($order);
                }

                return $this->redirectToRoute('order_payment');
            }
        }

        return $this->render('order/index.html.twig', [
            'order' => $order,
            'shipping_time_range' => $this->getShippingTimeRange($order),
            'pre_submit_errors' => $form->isSubmitted() ? null : ValidationUtils::serializeViolationList($orderErrors),
            'order_access_token' => $this->orderAccessTokenManager->create($order),
            'form' => $form->createView(),
            'form_tip' => $tipForm->createView(),
            'form_coupon' => $couponForm->createView(),
            'form_vytal' => $vytalForm->createView(),
            'form_loopeat_returns' => $loopeatReturnsForm->createView(),
        ]);
    }

    private function getShippingTimeRange(OrderInterface $order)
    {
        $range = $order->getShippingTimeRange();

        // Don't forget that $range may be NULL
        $shippingTimeRange = $range ? [
            $range->getLower()->format(\DateTime::ATOM),
            $range->getUpper()->format(\DateTime::ATOM),
        ] : null;

        return $shippingTimeRange;
    }

    /**
     * @Route("/order/payment", name="order_payment")
     */
    public function paymentAction(Request $request,
        OrderManager $orderManager,
        CartContextInterface $cartContext,
        StripeManager $stripeManager,
        SettingsManager $settingsManager,
        EmbedContext $embedContext)
    {
        if (!$settingsManager->get('guest_checkout_enabled')) {
            if (!$embedContext->isEnabled()) {
                $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
            }
        }

        $order = $cartContext->getCart();

        if (null === $order || !$order->hasVendor()) {

            return $this->redirectToRoute('homepage');
        }

        if (null === $order->getCustomer()) {

            return $this->redirectToRoute('order');
        }

        $orderErrors = $this->validator->validate($order);

        $checkoutPayment = new CheckoutPayment($order);
        $form = $this->createForm(CheckoutPaymentType::class, $checkoutPayment, [
            'csrf_protection' => 'test' !== $this->environment #FIXME; normally cypress e2e tests run with CSRF protection enabled, but once in a while it fails
        ]);

        $parameters =  [
            'order' => $order,
            'shipping_time_range' => $this->getShippingTimeRange($order),
            'pre_submit_errors' => $form->isSubmitted() ? null : ValidationUtils::serializeViolationList($orderErrors),
            'order_access_token' => $this->orderAccessTokenManager->create($order),
        ];

        $form->handleRequest($request);

        /**
         * added to debug issues with stripe payment:
         * https://github.com/coopcycle/coopcycle-web/issues/3688
         * https://github.com/coopcycle/coopcycle-app/issues/1603
         */
        if ($request->isMethod('POST')) {
            if ($form->isSubmitted()) {
                $this->checkoutLogger->info(sprintf('Order #%d | OrderController::paymentAction | isSubmitted: true, isValid: %d errors: %s',
                    $order->getId(), $form->isValid(), json_encode($form->getErrors()->__toString())));
            } else {
                $this->checkoutLogger->info(sprintf('Order #%d | OrderController::paymentAction | isSubmitted: false, errors: %s',
                    $order->getId(), json_encode($form->getErrors()->__toString())));
            }
        }

        // Keep a copy of the payments before trying authorization
        $payments = $order->getPayments()->filter(
            fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_CART);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = [
                'stripeToken' => $form->get('stripePayment')->get('stripeToken')->getData()
            ];

            if ($form->has('paymentMethod')) {
                $data['mercadopagoPaymentMethod'] = $form->get('paymentMethod')->getData();
            }
            if ($form->has('installments')) {
                $data['mercadopagoInstallments'] = $form->get('installments')->getData();
            }

            $orderManager->checkout($order, $data);

            $this->objectManager->flush();

            $failedPayments = $payments->filter(
                fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_FAILED);

            if (count($failedPayments) > 0) {
                $errors = $failedPayments->map(fn (PaymentInterface $payment): string => $payment->getLastError());
                $error = implode("\n", $errors->toArray());

                return $this->render('order/payment.html.twig', array_merge($parameters, [
                    'form' => $form->createView(),
                    'error' => $error,
                ]));
            }

            return $this->redirectToOrderConfirm($order);
        }

        $parameters['form'] = $form->createView();

        return $this->render('order/payment.html.twig', $parameters);
    }

    /**
     * @Route("/order/payment-method", name="order_select_payment_method", methods={"POST"})
     */
    public function selectPaymentMethodAction(Request $request,
        CartContextInterface $cartContext,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        EntityManagerInterface $entityManager,
        NormalizerInterface $normalizer,
        PaymentContext $paymentContext,
        OrderProcessorInterface $orderPaymentProcessor,
        Hashids $hashids8)
    {
        $order = $cartContext->getCart();

        if (null === $order || !$order->hasVendor()) {

            return new JsonResponse(['message' => 'Order does not exist or has no vendor'], 400);
        }

        if (null === $order->getCustomer()) {

            return new JsonResponse(['message' => 'Order does not have any customer'], 400);
        }

        return $this->selectPaymentMethodForOrder(
            $order,
            $request,
            $paymentMethodRepository,
            $entityManager,
            $normalizer,
            $paymentContext,
            $orderPaymentProcessor,
            $hashids8
        );
    }

    /**
     * @Route("/order/{hashid}/payment-method", name="order_by_hashid16_select_payment_method", methods={"POST"})
     */
    public function selectPaymentMethodByHashid16Action($hashid, Request $request,
        OrderRepository $orderRepository,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        EntityManagerInterface $entityManager,
        NormalizerInterface $normalizer,
        PaymentContext $paymentContext,
        OrderProcessorInterface $orderPaymentProcessor,
        Hashids $hashids16,
        Hashids $hashids8,
        StateMachineFactoryInterface $stateMachineFactory)
    {
        $decoded = $hashids16->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $order = $orderRepository->find($id);

        if (null === $order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $stateMachine = $stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $isExpectedState = $stateMachine->can(OrderTransitions::TRANSITION_CREATE) || $stateMachine->can(OrderTransitions::TRANSITION_FULFILL);
        if (!$isExpectedState) {
            throw new BadRequestHttpException(sprintf('Order #%d is not in an expected state', $id));
        }

        return $this->selectPaymentMethodForOrder(
            $order,
            $request,
            $paymentMethodRepository,
            $entityManager,
            $normalizer,
            $paymentContext,
            $orderPaymentProcessor,
            $hashids8
        );
    }

    /**
     * @Route("/order/confirm/{hashid}", name="order_confirm")
     */
    public function confirmAction($hashid,
        OrderRepository $orderRepository,
        FlashBagInterface $flashBag,
        JWSProviderInterface $jwsProvider,
        IriConverterInterface $iriConverter,
        SessionInterface $session,
        Filesystem $assetsFilesystem,
        CentrifugoClient $centrifugoClient)
    {
        $hashids = new Hashids($this->getParameter('secret'), 16);

        $decoded = $hashids->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $order = $orderRepository->find($id);

        if (null === $order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $this->denyAccessUnlessGranted('view_public', $order);

        // TODO Check if order is in expected state (new or superior)

        $loopeatAccessTokenKey =
            sprintf('loopeat.order.%d.access_token', $id);
        $loopeatRefreshTokenKey =
            sprintf('loopeat.order.%d.refresh_token', $id);

        if ($session->has($loopeatAccessTokenKey) && $session->has($loopeatRefreshTokenKey)) {

            $order->getCustomer()->setLoopeatAccessToken(
                $session->get($loopeatAccessTokenKey)
            );
            $order->getCustomer()->setLoopeatRefreshToken(
                $session->get($loopeatRefreshTokenKey)
            );

            $this->objectManager->flush();

            $session->remove($loopeatAccessTokenKey);
            $session->remove($loopeatRefreshTokenKey);
        }

        $dabbaAccessTokenKey =
            sprintf('dabba.order.%d.access_token', $id);
        $dabbaRefreshTokenKey =
            sprintf('dabba.order.%d.refresh_token', $id);

        if ($session->has($dabbaAccessTokenKey) && $session->has($dabbaRefreshTokenKey)) {

            $order->getCustomer()->setDabbaAccessToken(
                $session->get($dabbaAccessTokenKey)
            );
            $order->getCustomer()->setDabbaRefreshToken(
                $session->get($dabbaRefreshTokenKey)
            );

            $this->objectManager->flush();

            $session->remove($dabbaAccessTokenKey);
            $session->remove($dabbaRefreshTokenKey);
        }

        $resetSession = $flashBag->has('reset_session') && !empty($flashBag->get('reset_session'));
        $trackGoal = $flashBag->has('track_goal') && !empty($flashBag->get('track_goal'));

        // FIXME We may generate expired tokens

        $exp = clone $order->getShippingTimeRange()->getUpper();
        $exp->modify('+3 hours');

        $customMessage = null;
        try {
            if ($assetsFilesystem->fileExists('order_confirm.md')) {
                $customMessage = $assetsFilesystem->read('order_confirm.md');
            }
        } catch (UnableToCheckFileExistence $e) {
            $this->checkoutLogger->error($e->getMessage());
        }

        return $this->render('order/foodtech.html.twig', [
            'order' => $order,
            'events' => (new OrderEventCollection($order))->toArray(),
            'order_normalized' => $this->get('serializer')->normalize($order, 'jsonld', [
                'groups' => ['order'],
                'is_web' => true
            ]),
            'reset' => $resetSession,
            'track_goal' => $trackGoal,
            'custom_message' => $customMessage,
            'centrifugo' => [
                'token'   => $centrifugoClient->generateConnectionToken($order->getId(), $exp->getTimestamp()),
                'channel' => sprintf('%s_order_events#%d', $this->getParameter('centrifugo_namespace'), $order->getId())
            ]
        ]);
    }

    /**
     * @Route("/order/{hashid}/reorder", name="order_reorder")
     */
    public function reorderAction($hashid,
        OrderRepository $orderRepository,
        OrderProcessorInterface $orderProcessor,
        OrderModifierInterface $orderModifier,
        CartStorage $cartStorage)
    {
        $hashids = new Hashids($this->getParameter('secret'), 16);

        $decoded = $hashids->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $order = $orderRepository->find($id);

        if (null === $order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $restaurant = $order->getRestaurant();

        $cart = $this->orderFactory->createForRestaurant($restaurant);
        $this->checkoutLogger->info(sprintf('Order (cart) object created (created_at = %s) | OrderController',
            $cart->getCreatedAt()->format(\DateTime::ATOM)));

        $cart->setCustomer($this->getUser()->getCustomer());

        foreach ($order->getItems() as $item) {
            $orderModifier->addToOrder($cart, clone $item);
        }

        $orderProcessor->process($cart);

        $this->objectManager->persist($cart);
        $this->objectManager->flush();

        $this->checkoutLogger->info(sprintf('Order #%d (created_at = %s) created in the database (id = %d) | OrderController',
            $cart->getId(), $cart->getCreatedAt()->format(\DateTime::ATOM), $cart->getId()));

        $cartStorage->set($cart);

        return $this->redirectToRoute('order');
    }

    /**
     * @Route("/order/continue", name="order_continue")
     */
    public function continueAction(Request $request,
        CartContextInterface $cartContext)
    {
        $order = $cartContext->getCart();

        if (null === $order || !$order->hasVendor()) {

            return $this->redirectToRoute('homepage');
        }

        $restaurants = $order->getRestaurants();

        if (count($restaurants) === 0) {

            return $this->redirectToRoute('homepage');
        }

        return $this->redirectToRoute('restaurant', ['id' => $restaurants->first()->getId()]);
    }

    /**
     * @Route("/order/{hashid}/preview", name="order_preview")
     */
    public function dataPreviewAction($hashid, OrderRepository $orderRepository)
    {
        $hashids = new Hashids($this->getParameter('secret'), 16);

        $decoded = $hashids->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $order = $orderRepository->find($id);

        if (null === $order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $orderNormalized = $this->get('serializer')->normalize($order, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'address']
        ]);

        return new JsonResponse($orderNormalized, 200);
    }

    /**
     * @Route("/order/share/{slug}", name="public_share_order")
     */
    public function shareOrderAction($slug, Request $request,
        OrderTimeHelper $orderTimeHelper,
        CartStorage $cartStorage)
    {
        $invitation =
            $this->objectManager->getRepository(OrderInvitation::class)->findOneBy(['slug' => $slug]);

        if (null === $invitation) {
            throw $this->createNotFoundException();
        }

        $order = $invitation->getOrder();

        if ($order->getState() !== OrderInterface::STATE_CART) {
            throw $this->createAccessDeniedException();
        }

        // $this->denyAccessUnlessGranted('view_public', $order);

        // Hacky fix to correctly set the session and reload all the context
        if (!$cartStorage->has() || $cartStorage->get() !== $order) {
            $cartStorage->set($order);

            return $this->redirectToRoute($request->attributes->get('_route'), ['slug' => $slug]);
        }

        $cartForm = $this->createForm(CartType::class, $order);

        return $this->render('restaurant/index.html.twig', $this->auth([
            'restaurant' => $order->getRestaurant(),
            'restaurant_timing' => $this->timingRegistry->getAllFulfilmentMethodsForObject($order->getRestaurant()),
            'cart_form' => $cartForm->createView(),
            'cart_timing' => $orderTimeHelper->getTimeInfo($order),
            'order_access_token' => $this->orderAccessTokenManager->create($order),
            'addresses_normalized' => $this->getUserAddresses(),
            'is_player' => true,
        ]));
    }
}
