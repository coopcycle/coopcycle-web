<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Refund as RefundCommand;
use AppBundle\Payment\Gateway;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class RefundHandler
{
    private $gateway;
    private $stateMachineFactory;

    public function __construct(
        Gateway $gateway,
        StateMachineFactoryInterface $stateMachineFactory)
    {
        $this->gateway = $gateway;
        $this->stateMachineFactory = $stateMachineFactory;
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

        $refund = $this->gateway->refund($payment, $amount);

        if ($payment->getState() === 'refunded_partially' && $transition !== 'refund_partially') {
            $stateMachine->apply($transition);
        }

        $refund->setLiableParty($liableParty);
        $refund->setComments($comments);

        // TODO Record event
    }
}
