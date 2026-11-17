<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Exception\RefundException;
use AppBundle\Message\Order\Command\Refund as RefundCommand;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\GiftCard\OrderTransferReversal;
use AppBundle\Service\PaygreenManager;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class RefundHandler
{
    public function __construct(
        private Gateway $gateway,
        private GatewayResolver $gatewayResolver,
        private PaygreenClient $paygreenClient,
        private PaygreenManager $paygreenManager,
        private StateMachineFactoryInterface $stateMachineFactory,
        private OrderTransferReversal $orderTransferReversal)
    {}

    public function __invoke(RefundCommand $command)
    {
        $orderOrPayment = $command->getSubject();
        $amount = $command->getAmount();
        $liableParty = $command->getLiableParty();
        $comments = $command->getComments();

        if ($orderOrPayment instanceof PaymentInterface) {
            $this->refundPayment($orderOrPayment, $amount, $liableParty, $comments);
        } else {

            if (count($orderOrPayment->getPayments()) === 1) {
                $this->refundPayment(
                    $orderOrPayment->getLastPayment(PaymentInterface::STATE_COMPLETED),
                    $orderOrPayment->getTotal(),
                    $liableParty,
                    $comments
                );
            } else {
                $gateways = [];
                foreach ($orderOrPayment->getPayments() as $payment) {
                    if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                        $gateways[] = $this->gatewayResolver->resolveForPayment($payment);
                    }
                }

                $gateways = array_unique($gateways);

                // For PayGreen, the PaymentOrder *should* be the same for both payments
                if (count($gateways) === 1 && 'paygreen' === current($gateways)) {

                    foreach ($orderOrPayment->getPayments() as $payment) {
                        $refund = $payment->addRefund($payment->getAmount());
                        $refund->setLiableParty($liableParty);
                        $refund->setComments($comments);
                    }

                    $payment = $orderOrPayment->getLastPaymentByMethod('CARD', PaymentInterface::STATE_COMPLETED);
                    $details = $payment->getDetails();

                    $this->paygreenManager->authenticate();
                    $response = $this->paygreenClient->refundPaymentOrder($details['paygreen_payment_order_id']);

                    if ($response->getStatusCode() !== 200) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        throw new RefundException($data['message']);
                    }

                } else {
                    $this->refundSplitOrder(
                        $orderOrPayment,
                        $amount ?? $orderOrPayment->getTotal(),
                        $liableParty,
                        $comments
                    );
                }
            }
        }

        // The restaurant's side of the refund. Only bites when the co-op
        // transferred the money out itself -- which is what a gift-card
        // order does -- and only when the restaurant is the one bearing it.
        $order = $orderOrPayment instanceof PaymentInterface
            ? $orderOrPayment->getOrder()
            : $orderOrPayment;

        if (null !== $order) {
            $this->orderTransferReversal->reverseForRefund(
                $order,
                $amount ?? $order->getTotal(),
                $liableParty
            );
        }

        // TODO Record event
    }

    /**
     * Spreads a refund across the legs of a split order.
     *
     * Every leg used to be asked to refund the *order* total, which Stripe
     * rejects outright once a leg is smaller than the order -- exactly what
     * a gift card or a meal voucher makes happen. Each leg can only give
     * back what it took.
     *
     * Real money goes back first, store credit second. Returning cash before
     * a voucher is what a customer expects and what consumer-protection
     * rules assume; the other way round, the co-op would keep the cash and
     * hand back a gift card.
     */
    private function refundSplitOrder(
        $order,
        int $amount,
        string $liableParty = '',
        string $comments = ''
    ): void {
        $remaining = $amount;

        foreach ($this->refundablePayments($order) as $payment) {

            if ($remaining < 1) {
                break;
            }

            $share = min($remaining, $payment->getAmount());

            $this->refundPayment($payment, $share, $liableParty, $comments);

            $remaining -= $share;
        }
    }

    /**
     * @return PaymentInterface[] completed legs, real money first
     */
    private function refundablePayments($order): array
    {
        $payments = [];

        foreach ($order->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                $payments[] = $payment;
            }
        }

        usort($payments, fn (PaymentInterface $a, PaymentInterface $b): int =>
            self::refundOrder($a) <=> self::refundOrder($b));

        return $payments;
    }

    private static function refundOrder(PaymentInterface $payment): int
    {
        return 'GIFT_CARD' === $payment->getMethod()?->getCode() ? 1 : 0;
    }

    private function refundPayment(PaymentInterface $payment, ?int $amount = null, string $liableParty = '', string $comments = '')
    {
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

        if (!$stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
            throw new \Exception(sprintf('Payment #%d can\'t be refunded', $payment->getId()));
        }

        // FIXME With several partial refunds, need to check if totally refunded
        //
        // Compared against the *payment*, not the order: on a split order a
        // leg refunding all of itself is not a partial refund of that leg,
        // even though it is less than the order total.
        $isPartial = (int) $amount < $payment->getAmount();

        $transition = $isPartial ? 'refund_partially' : PaymentTransitions::TRANSITION_REFUND;

        $refund = $this->gateway->refund($payment, $amount);

        if ($payment->getState() === 'refunded_partially' && $transition !== 'refund_partially') {
            $stateMachine->apply($transition);
        }

        $refund->setLiableParty($liableParty);
        $refund->setComments($comments);
    }
}
