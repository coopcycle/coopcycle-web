<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
use AppBundle\Payment\Gateway;
use AppBundle\Service\LoggingUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class CheckoutHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private Gateway $gateway,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {
    }

    public function __invoke(Checkout $command)
    {
        $order = $command->getOrder();
        $stripeToken = $command->getStripeToken();

        // Assign a number to the order in any case
        $this->orderNumberAssigner->assignNumber($order);

        // Bail early if order is free
        if ($order->isFree()) {
            $event = new Event\CheckoutSucceeded($order);
            $this->eventBus->dispatch(
                (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
            );
            return;
        }

        $payments = $order->getPayments()->filter(
            fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_CART);

        // Make sure card payment is always *BEFORE* Edenred payment
        $iterator = $payments->getIterator();
        $iterator->uasort(function (PaymentInterface $a, PaymentInterface $b) {

            $methodA = $a->getMethod()->getCode();
            $methodB = $b->getMethod()->getCode();

            if ($methodA === $methodB) {

                return 0;
            }

            return $methodA === 'CARD' ? -1 : 1;
        });
        $payments = new ArrayCollection(iterator_to_array($iterator));

        foreach ($payments as $payment) {

            // Do nothing for "offline" payments, i.e cash on delivery
            if ($payment->isOffline()) {
                continue;
            }

            if ('approved' === $payment->getMercadopagoPaymentStatus()) {
                continue;
            }

            if ($payment->getMethod()->getCode() === 'CARD') {
                $data = $command->getData();
                if (is_array($data)) {
                    if (isset($data['mercadopagoPaymentMethod'])) {
                        $payment->setMercadopagoPaymentMethod($data['mercadopagoPaymentMethod']);
                    }
                    if (isset($data['mercadopagoInstallments'])) {
                        $payment->setMercadopagoInstallments($data['mercadopagoInstallments']);
                    }
                    if (isset($data['mercadopagoIssuer'])) {
                        $payment->setMercadopagoIssuer($data['mercadopagoIssuer']);
                    }
                    if (isset($data['mercadopagoPayerEmail'])) {
                        $payment->setMercadopagoPayerEmail($data['mercadopagoPayerEmail']);
                    }
                }
            }

            try {
                $this->gateway->authorize($payment, ['token' => $stripeToken]);
            } catch (\Exception $e) {
                $this->checkoutLogger->error(sprintf('CheckoutHandler | CheckoutFailed: %s', $e->getMessage()),
                    ['order' => $this->loggingUtils->getOrderId($order), 'exception' => $e]);

                $event = new Event\CheckoutFailed($order, $payment, $e->getMessage());
                $this->eventBus->dispatch(
                    (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
                );
                return;
            }
        }

        $event = new Event\CheckoutSucceeded($order, $payments);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
