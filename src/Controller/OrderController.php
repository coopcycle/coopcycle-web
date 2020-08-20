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
        if ($user !== $order->getCustomer()) {
            $order->setCustomer($user);
            $this->objectManager->flush();
        }

        $originalPromotionCoupon = $order->getPromotionCoupon();

        $form = $this->createForm(CheckoutAddressType::class, $order);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $order = $form->getData();

            if ($form->getClickedButton() && 'addTip' === $form->getClickedButton()->getName()) {
                $this->objectManager->flush();

                return $this->redirectToRoute('order');
            }

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

            if ($form->has('isJQuerySubmit') && $form->get('isJQuerySubmit')->getData()) {
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

        return $this->render('order/index.html.twig', array(
            'order' => $order,
            'shipping_range' => $this->getShippingRange($order),
            'form' => $form->createView(),
        ));
    }

    /**
     * @Route("/payment", name="order_payment")
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

            $this->addFlash('track_goal', true);

            return $this->redirectToRoute('profile_order', [
                'id' => $order->getId(),
                'reset' => 'yes'
            ]);
        }

        $parameters['form'] = $form->createView();

        return $this->render('order/payment.html.twig', $parameters);
    }
}
