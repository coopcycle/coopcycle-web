<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
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
        OrderTimeHelper $orderTimeHelper)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->stripeManager = $stripeManager;
        $this->orderTimeHelper = $orderTimeHelper;
    }

    private function setShippingDate(OrderInterface $order)
    {
        if (null === $order->getShippedAt()) {
            $asap = $this->orderTimeHelper->getAsap($order);
            $order->setShippedAt(new \DateTime($asap));
        }
    }

    public function __invoke(Checkout $command)
    {
        $order = $command->getOrder();
        $stripeToken = $command->getStripeToken();

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $isFreeOrder = null === $stripePayment && !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;

        if ($isFreeOrder) {
            $this->orderNumberAssigner->assignNumber($order);
            $this->setShippingDate($order);
            $this->eventRecorder->record(new Event\CheckoutSucceeded($order));

            return;
        }

        // TODO Check if $stripePayment !== null

        try {

            if ($stripePayment->getPaymentIntent()) {
                if ($stripePayment->getPaymentIntent() !== $stripeToken) {
                    $this->eventRecorder->record(new Event\CheckoutFailed($order, $stripePayment, 'Payment Intent mismatch'));
                    return;
                }

                if ($stripePayment->requiresUseStripeSDK()) {
                    $this->stripeManager->confirmIntent($stripePayment);
                }
            } else {
                $this->orderNumberAssigner->assignNumber($order);
                $stripePayment->setStripeToken($stripeToken);

                $charge = $this->stripeManager->authorize($stripePayment);

                $stripePayment->setCharge($charge->id);
            }

            $this->setShippingDate($order);

            $this->eventRecorder->record(new Event\CheckoutSucceeded($order, $stripePayment));

        } catch (\Exception $e) {
            $this->eventRecorder->record(new Event\CheckoutFailed($order, $stripePayment, $e->getMessage()));
        }
    }
}
