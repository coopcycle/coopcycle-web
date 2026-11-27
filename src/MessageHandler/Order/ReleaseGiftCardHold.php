<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Service\GiftCard\GiftCardManager;
use AppBundle\Sylius\Order\OrderInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Gives a customer their gift card balance back when the order it was
 * reserved for isn't going to happen.
 *
 * Modelled on CancelEdenredTransaction, with two differences, both
 * deliberate.
 *
 * The no-show check: when an order is cancelled for a no-show, UpdateState
 * *completes* its payments rather than cancelling them -- the customer is
 * charged for food that was made. Releasing the hold there would hand the
 * money back for a meal the restaurant cooked. (CancelEdenredTransaction has
 * no such check, which looks like a bug of its own; worth a separate issue
 * rather than a silent fix here.)
 *
 * And the replay guard, inside GiftCardManager::release(): these events get
 * redelivered, and crediting twice is how a customer ends up with more gift
 * card than they paid for.
 */
#[AsMessageHandler()]
class ReleaseGiftCardHold
{
    public function __construct(
        private readonly GiftCardManager $giftCardManager,
        private readonly StateMachineFactoryInterface $stateMachineFactory,
    ) {
    }

    public function __invoke(OrderRefused|OrderCancelled $event): void
    {
        $order = $event->getOrder();

        if ($event instanceof OrderCancelled
            && OrderInterface::CANCEL_REASON_NO_SHOW === $event->getReason()) {
            return;
        }

        if (null === $order->getGiftCard()) {
            return;
        }

        $giftCardPayments = $order->getPayments()->filter(fn (PaymentInterface $payment): bool =>
            $payment->getMethod()?->getCode() === 'GIFT_CARD'
                && in_array($payment->getState(), [
                    PaymentInterface::STATE_AUTHORIZED,
                    PaymentInterface::STATE_COMPLETED,
                ], true));

        foreach ($giftCardPayments as $payment) {

            $this->giftCardManager->release($payment);

            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

            if ($stateMachine->can(PaymentTransitions::TRANSITION_CANCEL)) {
                $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);
            }
        }
    }
}
