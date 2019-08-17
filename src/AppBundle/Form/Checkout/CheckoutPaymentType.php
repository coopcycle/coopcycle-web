<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\StripePaymentType;
use Symfony\Component\Form\FormBuilderInterface;

class CheckoutPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('stripePayment', StripePaymentType::class, [
                'mapped' => false,
            ]);
    }
}
