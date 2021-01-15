<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\RefuseOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Service\StripeManager;
use Sylius\Component\Payment\Model\PaymentInterface;
use SimpleBus\Message\Recorder\RecordsMessages;

class RefuseOrderHandler
{
    private $stripeManager;
    private $eventRecorder;

    public function __construct(StripeManager $stripeManager, RecordsMessages $eventRecorder)
    {
        $this->stripeManager = $stripeManager;
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(RefuseOrder $command)
    {
        $order = $command->getOrder();

        $completedPayment = $order->getLastPayment(PaymentInterface::STATE_COMPLETED);

        if (null !== $completedPayment && $completedPayment->isGiropay()) {
            $this->stripeManager->refund($completedPayment, null, true);
        }

        $this->eventRecorder->record(new Event\OrderRefused($order, $command->getReason()));
    }
}
