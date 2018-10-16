<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Refund;
use AppBundle\Domain\Order\Event;
use AppBundle\Service\StripeManager;
use SimpleBus\Message\Recorder\RecordsMessages;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;

class RefundHandler
{
    private $stripeManager;
    private $stateMachineFactory;
    private $eventRecorder;

    public function __construct(
        StripeManager $stripeManager,
        StateMachineFactoryInterface $stateMachineFactory,
        RecordsMessages $eventRecorder)
    {
        $this->stripeManager = $stripeManager;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(Refund $command)
    {
        $payment = $command->getPayment();

        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

        if (!$stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
            throw new \Exception(sprintf('Payment #%d can\'t be refunded', $payment->getId()));
        }

        try {

            $refund = $this->stripeManager->refund($payment);
            $stateMachine->apply(PaymentTransitions::TRANSITION_REFUND);

            // TODO Record event

        } catch (\Exception $e) {
            // TODO Record event
        }
    }
}
