<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
use AppBundle\Payment\Gateway;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class CheckoutHandler
{
    public function __construct(
        private RecordsMessages $eventRecorder,
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
            $this->eventRecorder->record(new Event\CheckoutSucceeded($order));
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
                }
            }

            try {
                $this->gateway->authorize($payment, ['token' => $stripeToken]);
            } catch (\Exception $e) {
                $this->checkoutLogger->error(sprintf('CheckoutHandler | CheckoutFailed: %s', $e->getMessage()),
                    ['order' => $this->loggingUtils->getOrderId($order), 'exception' => $e]);

                $this->eventRecorder->record(new Event\CheckoutFailed($order, $payment, $e->getMessage()));
                return;
            }
        }

        $this->eventRecorder->record(new Event\CheckoutSucceeded($order, $payments));
    }
}
