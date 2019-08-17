<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
use AppBundle\Service\StripeManager;
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

    public function __invoke(Checkout $command)
    {
        $order = $command->getOrder();

        $stripeToken = $command->getStripeToken();

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);

        // TODO Check if $stripePayment !== null

        // Assign order number now because it is needed for Stripe
        $this->orderNumberAssigner->assignNumber($order);

        $stripePayment->setStripeToken($stripeToken);

        try {

            $charge = $this->stripeManager->authorize($stripePayment);
            $stripePayment->setCharge($charge->id);

            if (null === $order->getShippedAt()) {
                $availabilities = $this->orderTimeHelper->getAvailabilities($order);
                $asap = $this->orderTimeHelper->getAsap($availabilities);

                $order->setShippedAt(new \DateTime($asap));
            }

            $this->eventRecorder->record(new Event\CheckoutSucceeded($order, $stripePayment));

        } catch (\Exception $e) {
            $this->eventRecorder->record(new Event\CheckoutFailed($order, $stripePayment, $e->getMessage()));
        }
    }
}
