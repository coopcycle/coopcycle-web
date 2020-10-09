<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\DataType\TsRange;
use AppBundle\Embed\Context as EmbedContext;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Form\Checkout\CheckoutAddressType;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderEventCollection;
use AppBundle\Utils\OrderTimeHelper;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrderController extends AbstractController
{
    use OrderConfirmTrait;

    private $objectManager;
    private $orderTimeHelper;
    private $logger;

    public function __construct(
        EntityManagerInterface $objectManager,
        OrderTimeHelper $orderTimeHelper,
        FactoryInterface $orderFactory,
        string $sessionKeyName,
        LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->orderFactory = $orderFactory;
        $this->sessionKeyName = $sessionKeyName;
        $this->logger = $logger;
    }

    private function getShippingRange(OrderInterface $order): TsRange
    {
        $range = $order->getShippingTimeRange();

        if (null !== $range) {

            return $range;
        }

        return $this->orderTimeHelper->getShippingTimeRange($order);
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
        EmbedContext $embedContext)
    {
        if (!$settingsManager->get('guest_checkout_enabled')) {
            if (!$embedContext->isEnabled()) {
                $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
            }
        }

        $order = $cartContext->getCart();

        if (null === $order || null === $order->getRestaurant()) {

            return $this->redirectToRoute('homepage');
        }

        $user = $this->getUser();

        // If the user is authenticated, use the corresponding customer
        // @see AppBundle\EventListener\WebAuthenticationListener
        if (null !== $user && $user->getCustomer() !== $order->getCustomer()) {
            $order->setCustomer($user->getCustomer());
            $this->objectManager->flush();
        }

        $originalPromotionCoupon = $order->getPromotionCoupon();
        $wasReusablePackagingEnabled = $order->isReusablePackagingEnabled();
        $originalReusablePackagingPledgeReturn = $order->getReusablePackagingPledgeReturn();

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

            $tipWasAdded =
                $form->getClickedButton() && 'addTip' === $form->getClickedButton()->getName();

            $promotionCouponWasAdded =
                null === $originalPromotionCoupon && null !== $order->getPromotionCoupon();

            // In those cases, we always reload the page
            if ($reusablePackagingWasChanged || $tipWasAdded || $promotionCouponWasAdded || $reusablePackagingPledgeReturnWasChanged) {

                if ($promotionCouponWasAdded) {
                    $this->addFlash(
                        'notice',
                        $translator->trans('promotions.promotion_coupon.success', [
                            '%code%' => $order->getPromotionCoupon()->getCode()
                        ])
                    );
                }

                $orderProcessor->process($order);
                $this->objectManager->flush();

                return $this->redirectToRoute('order');
            }

            if ($form->isValid()) {

                $orderProcessor->process($order);

                $isQuote = $form->getClickedButton() && 'quote' === $form->getClickedButton()->getName();
                $isFreeOrder = !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;

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
            'shipping_range' => $this->getShippingRange($order),
            'form' => $form->createView(),
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

        if (null === $order || null === $order->getRestaurant()) {

            return $this->redirectToRoute('homepage');
        }

        // Make sure to call StripeManager::configurePayment()
        // It will resolve the Stripe account that will be used
        $stripeManager->configurePayment(
            $order->getLastPayment(PaymentInterface::STATE_CART)
        );

        $form = $this->createForm(CheckoutPaymentType::class, $order);

        $parameters =  [
            'order' => $order,
            'restaurant' => $order->getRestaurant(),
            'shipping_range' => $this->getShippingRange($order),
        ];

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

            if ($payment->hasSource()) {

                $payment->setState(PaymentInterface::STATE_PROCESSING);

                // TODO Freeze shipping time?
                // Maybe better after source becomes chargeable

                $this->objectManager->flush();

                return $this->redirect($payment->getSourceRedirectUrl());
            }

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
     * @Route("/order/confirm/{hashid}", name="order_confirm")
     */
    public function confirmAction($hashid,
        OrderRepository $orderRepository,
        FlashBagInterface $flashBag,
        JWSProviderInterface $jwsProvider,
        IriConverterInterface $iriConverter,
        SessionInterface $session,
        Request $request)
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

            $session->remove($loopeatAccessTokenKey);
            $session->remove($loopeatRefreshTokenKey);
        }

        $resetSession = $flashBag->has('reset_session') && !empty($flashBag->get('reset_session'));
        $trackGoal = $flashBag->has('track_goal') && !empty($flashBag->get('track_goal'));

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

        return $this->render('order/foodtech.html.twig', [
            'order' => $order,
            'events' => (new OrderEventCollection($order))->toArray(),
            'order_normalized' => $this->get('serializer')->normalize($order, 'jsonld', [
                'groups' => ['order'],
                'is_web' => true
            ]),
            'reset' => $resetSession,
            'track_goal' => $trackGoal,
            'jwt' => $jwt,
        ]);
    }

    /**
     * @Route("/order/{hashid}/reorder", name="order_reorder")
     */
    public function reorderAction($hashid,
        OrderRepository $orderRepository,
        SessionInterface $session,
        OrderProcessorInterface $orderProcessor,
        OrderModifierInterface $orderModifier,
        Request $request)
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

        $session->set('restaurantId', $cart->getRestaurant()->getId());
        $session->set($this->sessionKeyName, $cart->getId());

        return $this->redirectToRoute('order');
    }
}
