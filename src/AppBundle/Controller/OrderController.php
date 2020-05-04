<?php

namespace AppBundle\Controller;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Checkout\CheckoutAddressType;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Service\OrderManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/order")
 */
class OrderController extends AbstractController
{
    private $objectManager;
    private $orderTimeHelper;
    private $logger;

    public function __construct(
        EntityManagerInterface $objectManager,
        OrderTimeHelper $orderTimeHelper,
        LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->orderTimeHelper = $orderTimeHelper;
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
     * @Route("/", name="order")
     * @Template()
     */
    public function indexAction(Request $request,
        OrderManager $orderManager,
        CartContextInterface $cartContext,
        OrderProcessorInterface $orderProcessor,
        TranslatorInterface $translator,
        ValidatorInterface $validator)
    {
        $order = $cartContext->getCart();

        if (null === $order || null === $order->getRestaurant()) {

            return $this->redirectToRoute('homepage');
        }

        $user = $this->getUser();

        // At this step, we are pretty sure the customer is logged in
        // Make sure the order actually has a customer, if not set previously
        // @see AppBundle\EventListener\WebAuthenticationListener
        if ($user !== $order->getUser()) {
            $order->setCustomer($user->getCustomer());
            $this->objectManager->flush();
        }

        $originalPromotionCoupon = $order->getPromotionCoupon();
        $originalReusablePackagingEnabled = $order->isReusablePackagingEnabled();

        $form = $this->createForm(CheckoutAddressType::class, $order);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $order = $form->getData();

            $orderProcessor->process($order);

            $promotionCoupon = $order->getPromotionCoupon();

            // Check if a promotion coupon has been added
            if (null === $originalPromotionCoupon && null !== $promotionCoupon) {
                $this->addFlash(
                    'notice',
                    $translator->trans('promotions.promotion_coupon.success', ['%code%' => $promotionCoupon->getCode()])
                );
            }

            $isQuote = $form->getClickedButton() && 'quote' === $form->getClickedButton()->getName();
            $isFreeOrder = !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;

            if ($isQuote) {
                $orderManager->quote($order);
            } elseif ($isFreeOrder) {
                $orderManager->checkout($order);
            }

            $this->objectManager->flush();

            if ($form->getClickedButton() && 'addPromotion' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('order');
            }

            if ($originalReusablePackagingEnabled !== $order->isReusablePackagingEnabled()) {
                return $this->redirectToRoute('order');
            }

            if ($isFreeOrder || $isQuote) {
                 $this->addFlash('track_goal', true);

                return $this->redirectToRoute('profile_order', [
                    'id' => $order->getId(),
                    'reset' => 'yes'
                ]);
            }

            return $this->redirectToRoute('order_payment');
        }

        $isLoopEatValid = true;
        if ($order->getRestaurant()->isLoopeatEnabled()) {
            $violations = $validator->validate($order, null, ['loopeat']);
            $isLoopEatValid = count($violations) === 0;
        }

        return array(
            'order' => $order,
            'shipping_range' => $this->getShippingRange($order),
            'form' => $form->createView(),
            'loopeat_valid' => $isLoopEatValid,
        );
    }

    /**
     * @Route("/payment", name="order_payment")
     * @Template()
     */
    public function paymentAction(Request $request,
        OrderManager $orderManager,
        CartContextInterface $cartContext,
        StripeManager $stripeManager)
    {
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

            $orderManager->checkout($order, $form->get('stripePayment')->get('stripeToken')->getData());

            $this->objectManager->flush();

            if (PaymentInterface::STATE_FAILED === $payment->getState()) {
                return array_merge($parameters, [
                    'form' => $form->createView(),
                    'error' => $payment->getLastError()
                ]);
            }

            $this->addFlash('track_goal', true);

            return $this->redirectToRoute('profile_order', [
                'id' => $order->getId(),
                'reset' => 'yes'
            ]);
        }

        $parameters['form'] = $form->createView();

        return $parameters;
    }
}
