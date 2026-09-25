<?php

namespace AppBundle\Api\State;

/**
 * @see InvoiceLineItemAmountCalculator
 */
final class InvoiceLineItemAmounts
{
    public function __construct(
        public readonly int $subTotal,
        public readonly int $tax,
        public readonly int $total,
        // Whether CoopCycle still needs to invoice this order's organization for it.
        // Always true for Store (on-demand delivery) orders. For restaurant orders,
        // true only when paid (fully or partially) by meal voucher — card payments
        // are already automatically settled via Stripe Connect.
        public readonly bool $needsInvoicing,
    )
    {
    }
}
