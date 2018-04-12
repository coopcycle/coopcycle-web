<?php

namespace AppBundle\Sylius\StripeTransfer;

use Sylius\Component\Payment\PaymentTransitions as BasePaymentTransitions;

class StripeTransferTransitions
{
    public const GRAPH = 'stripe_transfer';

    public const TRANSITION_COMPLETE  = BasePaymentTransitions::TRANSITION_COMPLETE;
    public const TRANSITION_FAIL  = BasePaymentTransitions::TRANSITION_FAIL;
    public const TRANSITION_REFUND = BasePaymentTransitions::TRANSITION_REFUND;

}