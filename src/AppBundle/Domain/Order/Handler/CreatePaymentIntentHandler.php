<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CreatePaymentIntent;
use AppBundle\Domain\Order\Event;
use AppBundle\Service\StripeManager;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe\Error\Base as StripeException;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class CreatePaymentIntentHandler
{
    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    public function __construct(
        RecordsMessages $eventRecorder,
        OrderNumberAssignerInterface $orderNumberAssigner,
        StripeManager $stripeManager)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->stripeManager = $stripeManager;
    }

    public function __invoke(CreatePaymentIntent $command)
    {
        $order = $command->getOrder();
        $paymentMethodId = $command->getPaymentMethodId();

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $stripePayment->setPaymentMethod($paymentMethodId);

        // TODO Check if $stripePayment !== null

        // Assign order number now because it is needed for Stripe
        $this->orderNumberAssigner->assignNumber($order);

        try {

            $intent = $this->stripeManager->createIntent($stripePayment);
            $stripePayment->setPaymentIntent($intent);

        } catch (StripeException $e) {
            $this->eventRecorder->record(new Event\CheckoutFailed($order, $stripePayment, $e->getMessage()));
        }
    }
}
