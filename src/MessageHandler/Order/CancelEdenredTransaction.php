<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Edenred\Client as EdenredClient;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class CancelEdenredTransaction
{
    private $edenred;
    private $stateMachineFactory;

    public function __construct(EdenredClient $edenred, StateMachineFactoryInterface $stateMachineFactory)
    {
        $this->edenred = $edenred;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    public function __invoke(OrderRefused|OrderCancelled $event)
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
