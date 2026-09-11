<?php

namespace AppBundle\Sylius\Payment;

/**
 * Payment method codes for meal voucher providers.
 *
 * Unlike card payments, which are automatically split via Stripe Connect
 * (the restaurant's share is transferred automatically, CoopCycle keeps its
 * platform fee as `application_fee_amount`), meal voucher payments are
 * collected by the restaurant directly, with no automatic split — so
 * CoopCycle has to separately invoice the restaurant for its platform fee.
 *
 * @see \AppBundle\Entity\Sylius\Payment::isMealVoucherComplement()
 * @see \AppBundle\Service\StripeManager::configureCreateIntentPayload()
 */
final class MealVoucherPaymentMethods
{
    public const CODES = ['EDENRED', 'CONECS', 'SWILE', 'RESTOFLASH'];
}
