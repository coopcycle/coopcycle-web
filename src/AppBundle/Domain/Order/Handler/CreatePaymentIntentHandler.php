<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CreatePaymentIntent;
use AppBundle\Domain\Order\Event;
use AppBundle\Service\StripeManager;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe\Exception\ApiErrorException;
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

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $payment->setPaymentMethod($paymentMethodId);

        // TODO Check if $payment !== null

        // Assign order number now because it is needed for Stripe
        $this->orderNumberAssigner->assignNumber($order);

        try {

            $intent = $this->stripeManager->createIntent($payment);
            $payment->setPaymentIntent($intent);

        } catch (ApiErrorException $e) {
            $this->eventRecorder->record(new Event\CheckoutFailed($order, $payment, $e->getMessage()));
        }
    }
}
