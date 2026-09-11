<?php

namespace AppBundle\Fixtures\AliceDataFixtures;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Payment\MealVoucherPaymentMethods;
use Fidry\AliceDataFixtures\ProcessorInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Adds one line item (with a random quantity) per product carried by the
 * restaurant to every foodtech order created in fixtures, then runs the
 * order through the real order processor so items/adjustments/taxes end up
 * computed exactly like a real cart (see CartItemProcessor). Also attaches a
 * completed payment to each order, deterministically alternating between a
 * meal voucher (EDENRED) and a card payment, so fixtures exercise both the
 * "needs invoicing" and "already settled" cases (see InvoiceLineItemAmountCalculator).
 */
final class FoodtechOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly PaymentFactoryInterface $paymentFactory,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly CurrencyContextInterface $currencyContext,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function preProcess(string $id, $object): void
    {
        // do nothing
    }

    /**
     * @inheritdoc
     */
    public function postProcess(string $id, $object): void
    {
        if (!$object instanceof Order) {
            return;
        }

        $order = $object;

        // Only process foodtech orders (restaurant orders), not on-demand delivery orders
        if (!$order->isFoodtech()) {
            return;
        }

        // Already has items (e.g. processed before, or set explicitly in fixtures)
        if (count($order->getItems()) > 0) {
            return;
        }

        $restaurant = $order->getRestaurant();
        if (null === $restaurant) {
            // Multi-vendor order, not supported here
            return;
        }

        foreach ($restaurant->getProducts() as $product) {
            $variant = $product->getVariants()->first();
            if (false === $variant) {
                continue;
            }

            $orderItem = $this->orderItemFactory->createNew();
            $orderItem->setVariant($variant);
            $orderItem->setUnitPrice($variant->getPrice());

            $this->orderItemQuantityModifier->modify($orderItem, random_int(1, 3));

            $this->orderModifier->addToOrder($order, $orderItem);
        }

        if ($order->isEmpty()) {
            return;
        }

        $this->orderProcessor->process($order);

        $this->addPayment($order, $id);

        // Changes are flushed inside FeatureContext
        // Flushing here makes tests too slow
    }

    private function addPayment(Order $order, string $id): void
    {
        // The real order processor chain (Sylius\Component\Order\Processor\OrderProcessorInterface,
        // run just above) includes OrderPaymentProcessor, which already upserts a
        // default (incomplete) payment on every order it processes. Replace it
        // with the completed payment we actually want for this fixture order.
        foreach ($order->getPayments()->toArray() as $existingPayment) {
            $order->removePayment($existingPayment);
        }

        // Deterministic (not random_int) so fixture-derived counts stay
        // reproducible across runs: every 3rd order is meal-voucher-paid
        preg_match('/(\d+)$/', $id, $matches);
        $isVoucherPaid = isset($matches[1]) && 0 === ((int) $matches[1]) % 3;

        $method = $this->paymentMethodRepository->findOneBy([
            'code' => $isVoucherPaid ? MealVoucherPaymentMethods::CODES[0] : 'CARD',
        ]);

        if (null === $method) {
            // Payment methods fixtures not loaded, skip gracefully
            return;
        }

        $payment = $this->paymentFactory->createWithAmountAndCurrencyCode(
            $order->getTotal(),
            $this->currencyContext->getCurrencyCode()
        );
        $payment->setMethod($method);
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $order->addPayment($payment);
    }
}
