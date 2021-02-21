<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Sylius\Order\OrderInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;

class CancelEdenredTransaction
{
    private $edenred;
    private $stateMachineFactory;

    public function __construct(EdenredClient $edenred, StateMachineFactoryInterface $stateMachineFactory)
    {
        $this->edenred = $edenred;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if (!$order->hasVendor()) {
            return;
        }

        $lastAuthorizedPayment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

        if (null === $lastAuthorizedPayment) {
            return;
        }

        $methods = ['EDENRED', 'EDENRED+CARD'];

        if (!in_array($lastAuthorizedPayment->getMethod()->getCode(), $methods)) {
            return;
        }

        $cancelId = $this->edenred->cancelTransaction($lastAuthorizedPayment);

        $lastAuthorizedPayment->setEdenredCancelId($cancelId);

        $stateMachine = $this->stateMachineFactory->get($lastAuthorizedPayment, PaymentTransitions::GRAPH);
        if ($stateMachine->can(PaymentTransitions::TRANSITION_CANCEL)) {
            $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);
        }
    }
}
