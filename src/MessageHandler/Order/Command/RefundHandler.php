<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Exception\RefundException;
use AppBundle\Message\Order\Command\Refund as RefundCommand;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
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
        private StateMachineFactoryInterface $stateMachineFactory)
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
                    foreach ($orderOrPayment->getPayments() as $payment) {
                        if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                            $this->refundPayment(
                                $payment,
                                $orderOrPayment->getTotal(),
                                $liableParty,
                                $comments
                            );
                        }
                    }
                }
            }
        }

        // TODO Record event
    }

    private function refundPayment(PaymentInterface $payment, int $amount = null, string $liableParty = '', string $comments = '')
    {
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
    }
}
