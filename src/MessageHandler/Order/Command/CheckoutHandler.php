<?php

namespace AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
use AppBundle\Payment\Gateway;
use AppBundle\Service\GiftCard\GiftCardManager;
use AppBundle\Service\LoggingUtils;
use AppBundle\Service\StripeManager;
use AppBundle\Utils\OrderTimeHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'command.bus')]
class CheckoutHandler
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private Gateway $gateway,
        private OrderTimeHelper $orderTimeHelper,
        private TranslatorInterface $translator,
        private GiftCardManager $giftCardManager,
        private StripeManager $stripeManager,
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

        // When the customer chose "ASAP", the cart has no shipping time range yet.
        // Lock the first available range *BEFORE* authorizing the payment,
        // otherwise the last range of the day may expire while the payment is processed,
        // and the order would be stored without a shipping time range.
        $hasAssignedShippingTimeRange = false;
        if ($order->hasVendor() && null === $order->getShippingTimeRange()) {

            $shippingTimeRange = $this->orderTimeHelper->getShippingTimeRange($order);

            if (null === $shippingTimeRange) {

                $this->checkoutLogger->warning('CheckoutHandler | no shipping time range available',
                    ['order' => $this->loggingUtils->getOrderId($order)]);

                $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
                if (!$order->isFree() && null !== $payment) {
                    $event = new Event\CheckoutFailed($order, $payment,
                        $this->translator->trans('order.shippedAt.notAvailable', [], 'validators'));
                    $this->eventBus->dispatch(
                        (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
                    );
                    return;
                }
            } else {
                $order->setShippingTimeRange($shippingTimeRange);
                $hasAssignedShippingTimeRange = true;
            }
        }

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

        // Authorize the legs in a deliberate order, cheapest-to-undo first.
        //
        // The gift card goes before the card. It is the only leg that can
        // fail for a reason the customer can act on -- the balance moved
        // between seeing the breakdown and paying -- and it fails locally,
        // with nothing to undo. Charging the card first and then discovering
        // the gift card is short would mean refunding real money because of
        // a race.
        //
        // Card before Edenred is the original rule, preserved.
        $iterator = $payments->getIterator();
        $iterator->uasort(fn (PaymentInterface $a, PaymentInterface $b): int =>
            self::authorizeOrder($a) <=> self::authorizeOrder($b));
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

                // Undo whatever the earlier legs already did. Without this a
                // failed split checkout leaves money reserved on a gift card
                // and a real authorization sitting on the customer's card
                // for about a week, for an order that never happened.
                $this->releaseAuthorizedPayments($payments, $payment);

                // Restore "ASAP" mode, the customer may retry later
                if ($hasAssignedShippingTimeRange) {
                    $order->setShippingTimeRange(null);
                }

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

    private static function authorizeOrder(PaymentInterface $payment): int
    {
        return match ($payment->getMethod()?->getCode()) {
            'GIFT_CARD' => 0,
            'CARD' => 1,
            default => 2,
        };
    }

    /**
     * Releases the legs authorized before the one that failed.
     *
     * Every step is wrapped: a cleanup that throws would replace the real
     * reason the checkout failed with whatever went wrong while tidying up,
     * and the customer would be told the wrong thing.
     */
    private function releaseAuthorizedPayments(iterable $payments, PaymentInterface $failed): void
    {
        foreach ($payments as $payment) {

            if ($payment === $failed || $payment->isOffline()) {
                continue;
            }

            try {
                if ('GIFT_CARD' === $payment->getMethod()?->getCode()) {
                    $this->giftCardManager->release($payment);
                } elseif ($payment->hasPaymentIntent()) {
                    $this->stripeManager->cancelIntent($payment);
                }
            } catch (\Exception $e) {
                $this->checkoutLogger->error(
                    sprintf('CheckoutHandler | could not release payment #%s: %s', $payment->getId(), $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }
    }
}
