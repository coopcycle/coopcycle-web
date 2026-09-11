<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Payment\MealVoucherPaymentMethods;
use Sylius\Component\Payment\Model\PaymentInterface;

/**
 * Computes the amount CoopCycle should invoice an order's organization for,
 * shared by InvoiceLineItemsProvider and InvoiceLineItemsGroupedByOrganizationProvider
 * so the two providers can't drift apart.
 *
 * - Store (on-demand delivery) orders: the full order amount, as today —
 *   Stores are invoiced for the delivery service itself, unrelated to how
 *   the order's customer paid.
 * - Restaurant (foodtech) orders paid by card are automatically settled via
 *   Stripe Connect (the restaurant's share is transferred automatically,
 *   CoopCycle keeps its platform fee as `application_fee_amount`) — nothing
 *   left to invoice.
 * - Restaurant orders paid (fully or partially) by meal voucher never go
 *   through that split: the restaurant keeps 100% of the voucher amount
 *   directly, so CoopCycle has to separately invoice the restaurant for its
 *   platform fee (`Order::getFeeTotal()`), which has no independent tax
 *   breakdown in this data model.
 */
final class InvoiceLineItemAmountCalculator
{
    public function compute(Order $order, ?LocalBusiness $restaurant): InvoiceLineItemAmounts
    {
        if (null === $restaurant) {
            return new InvoiceLineItemAmounts(
                $order->getTotal() - $order->getTaxTotal(),
                $order->getTaxTotal(),
                $order->getTotal(),
                true
            );
        }

        $hasVoucherPayment = null !== $order->getLastPaymentByMethod(
            MealVoucherPaymentMethods::CODES,
            PaymentInterface::STATE_COMPLETED
        );

        if (!$hasVoucherPayment) {
            // Already settled automatically via Stripe Connect, nothing owed
            return new InvoiceLineItemAmounts(0, 0, 0, false);
        }

        $feeTotal = $order->getFeeTotal();

        return new InvoiceLineItemAmounts($feeTotal, 0, $feeTotal, true);
    }
}
