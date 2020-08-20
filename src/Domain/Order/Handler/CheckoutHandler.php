<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
use AppBundle\Payment\Gateway;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class CheckoutHandler
{
    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    public function __construct(
        RecordsMessages $eventRecorder,
        OrderNumberAssignerInterface $orderNumberAssigner,
        StripeManager $stripeManager,
        Gateway $gateway,
        OrderTimeHelper $orderTimeHelper)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->stripeManager = $stripeManager;
        $this->gateway = $gateway;
        $this->orderTimeHelper = $orderTimeHelper;
    }

    private function setShippingDate(OrderInterface $order)
    {
        if (null === $order->getShippingTimeRange()) {
            $range = $this->orderTimeHelper->getShippingTimeRange($order);
            $order->setShippingTimeRange($range);
        }
    }

    private function getLastPayment(OrderInterface $order): ?PaymentInterface
    {
        if ($payment = $order->getLastPayment(PaymentInterface::STATE_CART)) {

            return $payment;
        }

        if ($payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING)) {

            return $payment;
        }

        return null;
    }

    public function __invoke(Checkout $command)
    {
        $order = $command->getOrder();
        $stripeToken = $command->getStripeToken();

        $payment = $this->getLastPayment($order);

        $isFreeOrder = null === $payment && !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;

        if ($isFreeOrder) {
            $this->orderNumberAssigner->assignNumber($order);
            $this->setShippingDate($order);
            $this->eventRecorder->record(new Event\CheckoutSucceeded($order));

            return;
        }

        // TODO Check if $payment !== null

        try {

            if ($payment->getPaymentIntent()) {
                if ($payment->getPaymentIntent() !== $stripeToken) {
                    $this->eventRecorder->record(new Event\CheckoutFailed($order, $payment, 'Payment Intent mismatch'));
                    return;
                }

                if ($payment->requiresUseStripeSDK()) {
                    $this->stripeManager->confirmIntent($payment);
                }
            } else {
                $this->orderNumberAssigner->assignNumber($order);
                $payment->setStripeToken($stripeToken);

                $data = $command->getData();

                if (is_array($data)) {
                    if (isset($data['mercadopagoPaymentMethod'])) {
                        $payment->setMercadopagoPaymentMethod($data['mercadopagoPaymentMethod']);
                    }
                    if (isset($data['mercadopagoInstallments'])) {
                        $payment->setMercadopagoInstallments($data['mercadopagoInstallments']);
                    }
                }

                $this->gateway->authorize($payment);
            }

            $this->setShippingDate($order);

            $this->eventRecorder->record(new Event\CheckoutSucceeded($order, $payment));

        } catch (\Exception $e) {
            $this->eventRecorder->record(new Event\CheckoutFailed($order, $payment, $e->getMessage()));
        }
    }
}
