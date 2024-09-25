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

        $authorizedEdenredPayments = $order->getPayments()->filter(function (PaymentInterface $payment): bool {
            return $payment->getMethod()->getCode() === 'EDENRED'
                && $payment->getState() === PaymentInterface::STATE_AUTHORIZED;
        });

        if (count($authorizedEdenredPayments) === 0) {
            return;
        }

        foreach ($authorizedEdenredPayments as $payment) {
            $cancelId = $this->edenred->cancelTransaction($payment);

            $payment->setEdenredCancelId($cancelId);

            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
            if ($stateMachine->can(PaymentTransitions::TRANSITION_CANCEL)) {
                $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);
            }
        }
    }
}
