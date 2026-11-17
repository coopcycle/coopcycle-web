<?php

namespace AppBundle\Payment\Gateway;

use AppBundle\Entity\Refund;
use AppBundle\Message\GiftCard\SettleGiftCardOrder;
use AppBundle\Payment\GatewayInterface;
use AppBundle\Service\GiftCard\GiftCardManager;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The gift-card leg of a split order, shaped like Gateway\Edenred.
 *
 * The timing is the thing to understand here, and it matches Edenred's: the
 * *ledger* moves at authorize, when the customer checks out, but *money*
 * only moves at capture, when the order is fulfilled. So an order that is
 * cancelled releases the hold without a single Stripe call, and a customer
 * never has to wait for a refund of money that never went anywhere.
 */
class GiftCard implements GatewayInterface
{
    public function __construct(
        private readonly GiftCardManager $giftCardManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Reserves the amount on the card.
     *
     * Checkout authorizes this leg before any card is charged, deliberately:
     * it is the only leg that can fail for a reason the customer can do
     * something about, and failing it first means we never have to refund a
     * real card because a gift card balance moved underneath us.
     */
    public function authorize(PaymentInterface $payment, array $context = [])
    {
        $hold = $this->giftCardManager->reserve($payment);

        $payment->setGiftCardCode($payment->getOrder()->getGiftCard()->getCode());
        $payment->setGiftCardTransactionId($hold->getId());
    }

    public function capture(PaymentInterface $payment)
    {
        $this->giftCardManager->capture($payment);

        $order = $payment->getOrder();

        // When a card covers the whole order there is no card charge, so
        // there is no charge.captured webhook either -- and that webhook is
        // what normally tells us to pay the restaurant. Nothing else is
        // coming, so this leg has to say so itself.
        //
        // When there *is* a complement, we stay quiet: the webhook settles
        // the order once it knows the real Stripe fee, which the amount
        // depends on.
        if (null === $order->getLastPaymentByMethod('CARD')) {
            $this->logger->info(sprintf(
                'Order #%d | fully covered by a gift card, settling from capture',
                $order->getId()
            ));

            $this->messageBus->dispatch(new SettleGiftCardOrder($order->getId()));
        }
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        $refund = $payment->addRefund($amount);

        $this->giftCardManager->release($payment, $amount);

        $refund->setData(['gift_card_code' => $payment->getGiftCardCode()]);

        return $refund;
    }
}
