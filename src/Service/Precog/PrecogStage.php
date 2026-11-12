<?php

namespace AppBundle\Service\Precog;

/**
 * How far along the checkout funnel a cart is.
 *
 * The rungs are ordered, and the order is the whole point: everything the
 * dispatch board draws -- pulse rate, opacity, size -- is derived from the rank,
 * and how precisely we are allowed to place the customer is derived from it too.
 *
 * A stage is never taken from the browser as-is. The checkout pages declare
 * which page they are, and PrecogTracker decides which rung that cart is
 * actually on.
 */
enum PrecogStage: string
{
    /** Shipping address set on the cart, customer still on the menu. */
    case CartAddress = 'cart.address';

    /** Landed on /order/, but the cart is not ready to be paid for yet. */
    case CheckoutOpen = 'checkout.open';

    /** On /order/, with an address, a time slot and a customer. */
    case CheckoutReady = 'checkout.ready';

    /** Landed on /order/payment. */
    case PaymentOpen = 'payment.open';

    /** Payment confirmation in flight. */
    case PaymentSubmitted = 'payment.submitted';

    /**
     * 1..5. The dispatch board styles the halo off this, so that adding a rung
     * later does not mean re-deriving the visual ladder in JavaScript.
     */
    public function rank(): int
    {
        return match ($this) {
            self::CartAddress      => 1,
            self::CheckoutOpen     => 2,
            self::CheckoutReady    => 3,
            self::PaymentOpen      => 4,
            self::PaymentSubmitted => 5,
        };
    }

    /**
     * The radius, in metres, the published position is rounded to.
     *
     * This is a privacy decision before it is a visual one: showing a dispatcher
     * roughly where a stranger lives before that stranger has bought anything is
     * the part that needs justifying. The position gets finer as the customer
     * commits, and an exact address only ever appears once the order exists --
     * at which point it is an ordinary task, not a ghost.
     */
    public function precisionInMeters(): int
    {
        return match ($this) {
            self::CartAddress      => 1200,
            self::CheckoutOpen     => 800,
            self::CheckoutReady    => 500,
            self::PaymentOpen      => 250,
            self::PaymentSubmitted => 100,
        };
    }
}
