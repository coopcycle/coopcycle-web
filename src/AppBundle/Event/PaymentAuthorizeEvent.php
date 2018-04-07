<?php

namespace AppBundle\Event;

use AppBundle\Entity\StripePayment;
use Symfony\Component\EventDispatcher\GenericEvent;

class PaymentAuthorizeEvent extends GenericEvent
{
    const NAME = 'payment.authorize';

    public function __construct(StripePayment $stripePayment)
    {
        parent::__construct($stripePayment);
    }

    public function getPayment()
    {
        return $this->getSubject();
    }
}
