<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\DataType\TsRange;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Embed\Context as EmbedContext;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Form\Checkout\CheckoutAddressType;
use AppBundle\Form\Checkout\CheckoutCouponType;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Form\Checkout\CheckoutTipType;
use AppBundle\Form\Checkout\CheckoutVytalType;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderEventCollection;
use AppBundle\Validator\Constraints\ShippingAddress as ShippingAddressConstraint;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use phpcent\Client as CentrifugoClient;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrderController extends AbstractController
{
    use OrderConfirmTrait;

    private $objectManager;

    public function __construct(
        EntityManagerInterface $objectManager,
        FactoryInterface $orderFactory,
        string $sessionKeyName)
    {
        $this->objectManager = $objectManager;
        $this->orderFactory = $orderFactory;
        $this->sessionKeyName = $sessionKeyName;
    }

    /**
     * @Route("/order/", name="order")
     */
    public function indexAction(Request $request,
        OrderManager $orderManager,
        CartContextInterface $cartContext,
        OrderProcessorInterface $orderProcessor,
        TranslatorInterface $translator,
        ValidatorInterface $validator,
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

        $errors = $validator->validate($order);

        // @see https://github.com/coopcycle/coopcycle-web/issues/2069
        if (count($errors->findByCodes(ShippingAddressConstraint::ADDRESS_NOT_SET)) > 0) {

            $vendor = $order->getVendor();
            if ($order->isMultiVendor()) {
                return $this->redirectToRoute('hub', ['id' => $vendor->getHub()->getId()]);
            }

            return $this->redirectToRoute('restaurant', ['id' => $vendor->getRestaurant()->getId()]);
        }

        $user = $this->getUser();

        // If the user is authenticated, use the corresponding customer
        // @see AppBundle\EventListener\WebAuthenticationListener
        if (null !== $user && $user->getCustomer() !== $order->getCustomer()) {

            $order->setCustomer($user->getCustomer());

            // Make sure to move LoopEat credentials if any
            $loopeatAccessTokenKey =
                sprintf('loopeat.order.%d.access_token', $order->getId());
            $loopeatRefreshTokenKey =
                sprintf('loopeat.order.%d.refresh_token', $order->getId());

            if ($session->has($loopeatAccessTokenKey) && $session->has($loopeatRefreshTokenKey)) {
                $order->getCustomer()->setLoopeatAccessToken(
                    $session->get($loopeatAccessTokenKey)
                );
                $order->getCustomer()->setLoopeatRefreshToken(
                    $session->get($loopeatRefreshTokenKey)
                );
            }

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

            if ($session->has($loopeatAccessTokenKey) && $session->has($loopeatRefreshTokenKey)) {
                $session->remove($loopeatAccessTokenKey);
                $session->remove($loopeatRefreshTokenKey);
            }

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

        if ($tipForm->isSubmitted()) {

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

        $form = $this->createForm(CheckoutAddressType::class, $order);
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
                }

                $orderProcessor->process($order);
                $this->objectManager->flush();

                return $this->redirectToRoute('order');
            }

            if ($form->isValid()) {

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

        return $this->render('order/index.html.twig', array(
            'order' => $order,
            'form' => $form->createView(),
            'form_tip' => $tipForm->createView(),
            'form_coupon' => $couponForm->createView(),
            'form_vytal' => $vytalForm->createView(),
        ));
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

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        // Make sure to call StripeManager::configurePayment()
        // It will resolve the Stripe account that will be used
        // TODO Make sure we are using Stripe, not MercadoPago
        $stripeManager->configurePayment($payment);

        $form = $this->createForm(CheckoutPaymentType::class, $order);

        $parameters =  [
            'order' => $order,
            'payment' => $payment,
        ];

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

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

            if (PaymentInterface::STATE_FAILED === $payment->getState()) {
                return $this->render('order/payment.html.twig', array_merge($parameters, [
                    'form' => $form->createView(),
                    'error' => $payment->getLastError()
                ]));
            }

            return $this->redirectToOrderConfirm($order);
        }

        $parameters['form'] = $form->createView();

        return $this->render('order/payment.html.twig', $parameters);
    }

    /**
     * @Route("/order/payment/{hashId}/method", name="order_payment_select_method", methods={"POST"})
     */
    public function selectPaymentMethodAction($hashId, Request $request,
        OrderManager $orderManager,
        CartContextInterface $cartContext,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        EntityManagerInterface $entityManager,
        EdenredClient $edenredClient)
    {
        $hashids = new Hashids($this->getParameter('secret'), 8);

        $decoded = $hashids->decode($hashId);

        if (count($decoded) !== 1) {

            return new JsonResponse(['message' => 'Hashid could not be decoded'], 400);
        }

        $paymentId = current($decoded);
        $payment = $entityManager->getRepository(PaymentInterface::class)->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['message' => 'Payment does not exist'], 404);
        }

        $order = $payment->getOrder();

        if (null === $order) {

            return new JsonResponse(['message' => 'Payment does not belong to any order'], 400);
        }

        $content = $request->getContent();

        $data = [];

        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (!isset($data['method'])) {

            return new JsonResponse(['message' => 'No payment method found in request'], 400);
        }

        $code = strtoupper($data['method']);

        $paymentMethod = $paymentMethodRepository->findOneByCode($code);

        if (null === $paymentMethod) {

            return new JsonResponse(['message' => 'Payment method does not exist'], 404);
        }

        // The "CASH_ON_DELIVERY" payment method may not be enabled,
        // however if it's enabled at shop level, it is allowed
        $bypass = $code === 'CASH_ON_DELIVERY' && $order->supportsCashOnDelivery();

        if (!$paymentMethod->isEnabled() && !$bypass) {

            return new JsonResponse(['message' => 'Payment method is not enabled'], 400);
        }

        $payment->setMethod($paymentMethod);

        switch ($code) {
            case 'EDENRED+CARD':
            case 'EDENRED':
                $breakdown = $edenredClient->splitAmounts($order);
                $payment->setAmountBreakdown($breakdown['edenred'], $breakdown['card']);
                break;
            default:
                $payment->clearAmountBreakdown();
                break;
        }

        $entityManager->flush();

        return new JsonResponse([
            'amount_breakdown' => $payment->getAmountBreakdown(),
        ]);
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
        if ($assetsFilesystem->has('order_confirm.md')) {
            $customMessage = $assetsFilesystem->read('order_confirm.md');
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
        SessionInterface $session,
        OrderProcessorInterface $orderProcessor,
        OrderModifierInterface $orderModifier)
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
        $cart->setCustomer($this->getUser()->getCustomer());

        foreach ($order->getItems() as $item) {
            $orderModifier->addToOrder($cart, clone $item);
        }

        $orderProcessor->process($cart);

        $this->objectManager->persist($cart);
        $this->objectManager->flush();

        $session->set($this->sessionKeyName, $cart->getId());

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
}
