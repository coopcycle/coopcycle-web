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
        // How CoopCycle's cut on this order was collected. `paid` is what was
        // already received automatically (Stripe Connect's application fee),
        // `unpaid` what still has to be invoiced to the organization. Exactly
        // one of the two is non-zero: Stripe takes no application fee at all on
        // an order carrying a meal voucher (see StripeManager::configureCreateIntentPayload()),
        // so there is never a partial split.
        public readonly int $paid,
        public readonly int $unpaid,
    )
    {
    }
}
