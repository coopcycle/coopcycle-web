<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Refund as RefundCommand;
use AppBundle\Domain\Order\Event;
use AppBundle\Entity\Refund;
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

    public function __invoke(RefundCommand $command)
    {
        $payment = $command->getPayment();
        $amount = $command->getAmount();
        $liableParty = $command->getLiableParty();
        $comments = $command->getComments();

        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

        if (!$stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
            throw new \Exception(sprintf('Payment #%d can\'t be refunded', $payment->getId()));
        }

        // FIXME With several partial refunds, need to check if totally refunded
        $isPartial = (int) $amount < $payment->getOrder()->getTotal();

        $transition = $isPartial ? 'refund_partially' : PaymentTransitions::TRANSITION_REFUND;

        $refund = $this->stripeManager->refund($payment, $amount);

        if ($payment->getState() === 'refunded_partially' && $transition !== 'refund_partially') {
            $stateMachine->apply($transition);
        }

        $ref = $payment->addRefund($amount, $liableParty, $comments);

        $ref->setData(['stripe_refund_id' => $refund->id]);
        $payment->addStripeRefund($refund);

        // TODO Record event
    }
}
